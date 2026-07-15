<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class InvoiceController extends Controller
{
    public function createInvoice(Request $request)
    {
        $company_id     = intval($request->input('company_id', 0));
        $customer_id    = intval($request->input('customer_id', 0));
        $customer_name  = trim($request->input('customer_name', ''));
        if ($customer_name === "") $customer_name = "Customer";
        $customer_phone = trim($request->input('customer_phone', ''));
        $cashier_id     = intval($request->input('cashier_id', 0));
        $products       = $request->input('products', []);
        $sub_total      = floatval($request->input('sub_total', 0));
        $gst_total      = floatval($request->input('gst_total', 0));
        $total_amount   = floatval($request->input('total_amount', 0));
        $paid_amount    = floatval($request->input('paid_amount', 0));
        $payment_method = trim($request->input('payment_method', 'cash'));
        $payment_type   = trim($request->input('payment_type', 'cash'));
        $gst_type       = trim($request->input('gst_type', 'without_gst'));
        $gst_no         = trim($request->input('gst_no', ''));
        $invoice_no     = "INV-" . time();

        /* VALIDATION */
        if (empty($customer_name) && empty($customer_phone)) {
            return response()->json(["status" => false, "message" => "Customer name or phone required"]);
        }
        if (!empty($customer_phone) && !preg_match('/^[0-9]{10}$/', $customer_phone)) {
            return response()->json(["status" => false, "message" => "Invalid phone number"]);
        }
        if (count($products) == 0) {
            return response()->json(["status" => false, "message" => "No products"]);
        }

        /* GST CONTROL */
        if ($gst_type === "without_gst") {
            $gst_total    = 0;
            $total_amount = $sub_total;
        }

        /* CREDIT / CASH LOGIC */
        $advance_balance = 0.0;
        if ($customer_id > 0) {
            $cust = Customer::find($customer_id);
            if ($cust) {
                $advance_balance = floatval($cust->advance_balance);
            }
        }
        $advance_used    = min($advance_balance, $total_amount);
        $effective_total = $total_amount - $advance_used;

        if ($payment_type === "credit") {
            $final_paid      = $advance_used;
            $balance_amount  = $effective_total;
            $payment_status  = $balance_amount <= 0 ? "paid" : ($advance_used > 0 ? "partial" : "not_paid");
            $advance_delta   = -$advance_used;
        } else {
            $total_received  = $paid_amount + $advance_used;

            if ($total_received >= $total_amount) {
                $final_paid     = $total_amount;
                $balance_amount = 0;
                $payment_status = "paid";
                $extra          = $total_received - $total_amount;
                $advance_delta  = $extra - $advance_used;
            } else {
                $final_paid     = $total_received;
                $balance_amount = $total_amount - $total_received;
                $payment_status = "partial";
                $advance_delta  = -$advance_used;
            }
        }

        /* DUE DATE */
        $due_date = null;
        if ($payment_type === "credit") {
            $credit_days = 0;
            if ($customer_id > 0) {
                $cust = Customer::find($customer_id);
                if ($cust) {
                    $credit_days = intval($cust->credit_days);
                }
            }
            $due_date = $credit_days > 0
                ? date('Y-m-d', strtotime("+$credit_days days"))
                : date('Y-m-d');
        }

        /* STOCK CHECK */
        foreach ($products as $item) {
            $product_id = intval($item['product_id']);
            $qty        = floatval($item['qty']);
            $prod = Product::where('id', $product_id)->where('company_id', $company_id)->where('is_deleted', 0)->first();
            if (!$prod) {
                return response()->json(["status" => false, "message" => "Invalid product"]);
            }
            if (floatval($prod->stock) < $qty) {
                return response()->json(["status" => false, "message" => "Stock not enough"]);
            }
        }

        /* PREVIOUS BALANCE */
        $previous_balance = 0;
        if ($customer_id > 0) {
            $previous_balance = floatval(Invoice::where('customer_id', $customer_id)
                ->where('balance_amount', '>', 0)
                ->sum('balance_amount'));
        }
        $current_balance = $previous_balance + $balance_amount;

        DB::beginTransaction();
        try {
            /* INSERT INVOICE */
            $invoice = Invoice::create([
                'invoice_no' => $invoice_no,
                'customer_id' => $customer_id > 0 ? $customer_id : null,
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'cashier_id' => $cashier_id,
                'products' => $products,
                'sub_total' => $sub_total,
                'gst_total' => $gst_total,
                'total_amount' => $total_amount,
                'paid_amount' => $final_paid,
                'balance_amount' => $balance_amount,
                'previous_balance' => $previous_balance,
                'current_balance' => $current_balance,
                'payment_method' => $payment_method,
                'payment_type' => $payment_type,
                'gst_type' => $gst_type,
                'gst_no' => $gst_no ?: null,
                'payment_status' => $payment_status,
                'company_id' => $company_id,
                'due_date' => $due_date,
                'created_at' => now()
            ]);

            /* INSERT PAYMENT */
            Payment::create([
                'company_id' => $company_id,
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice_no,
                'customer_id' => $customer_id > 0 ? $customer_id : 0,
                'total_amount' => $total_amount,
                'paid_amount' => $final_paid,
                'balance_amount' => $balance_amount,
                'payment_method' => $payment_method,
                'payment_status' => $payment_status,
                'notes' => ''
            ]);

            /* DEDUCT STOCK */
            foreach ($products as $item) {
                $pid = intval($item['product_id']);
                $qty = floatval($item['qty']);
                Product::where('id', $pid)->decrement('stock', $qty);
            }

            /* UPDATE CUSTOMER */
            $total_pending = $current_balance;
            if ($customer_id > 0) {
                $cust = Customer::find($customer_id);
                if ($cust) {
                    $new_advance = max(0.0, floatval($cust->advance_balance) + $advance_delta);
                    $cust->advance_balance = $new_advance;

                    if ($payment_type !== "credit") {
                        $points = floor($total_amount / 100);
                        if ($points > 0) {
                            $cust->loyalty_points = intval($cust->loyalty_points) + $points;
                        }
                    }
                    $cust->pending_amount = $total_pending;
                    $cust->save();
                }
            }

            DB::commit();

            /* LAST INVOICE */
            $last_invoice = null;
            if ($customer_id > 0) {
                $last_invoice = Invoice::where('customer_id', $customer_id)
                    ->where('company_id', $company_id)
                    ->where('id', '!=', $invoice->id)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            return response()->json([
                "status"         => true,
                "invoice_no"     => $invoice_no,
                "invoice_id"     => $invoice->id,
                "advance_used"   => $advance_used,
                "advance_delta"  => $advance_delta,
                "balance_amount" => $balance_amount,
                "payment_status" => $payment_status,
                "pending_amount" => $total_pending,
                "last_invoice"   => $last_invoice,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["status" => false, "message" => $e->getMessage()]);
        }
    }

    public function getAllInvoice(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));
        if (!$company_id) {
            return response()->json(["status" => false, "message" => "company_id required"]);
        }

        $invoices = DB::table('invoices as i')
            ->leftJoin('users as u', 'i.cashier_id', '=', 'u.id')
            ->select('i.*', 'u.name as cashier_name')
            ->where('i.company_id', $company_id)
            ->orderBy('i.id', 'desc')
            ->get();

        $data = [];
        foreach ($invoices as $row) {
            $rowArray = (array)$row;
            $rowArray['products'] = json_decode($rowArray['products']);
            $data[] = $rowArray;
        }

        return response()->json([
            "status" => true,
            "data" => $data
        ]);
    }

    public function getFilteredInvoices(Request $request)
    {
        $company_id     = intval($request->input('company_id', 0));
        $from_date      = $request->input('from_date', '');
        $to_date        = $request->input('to_date', '');
        $payment_method = $request->input('payment_method', 'all');
        $payment_status = $request->input('payment_status', 'all');
        $customer_name  = trim($request->input('customer_name', ''));
        $brand_id       = intval($request->input('brand_id', 0));

        if (!$company_id) {
            return response()->json(["status" => false, "message" => "company_id required"]);
        }

        $query = DB::table('invoices as i')
            ->leftJoin('users as u', 'i.cashier_id', '=', 'u.id')
            ->leftJoin('companies as c', 'i.company_id', '=', 'c.id')
            ->select('i.*', 'u.name as cashier_name', 'c.gstin')
            ->where('i.company_id', $company_id);

        if ($from_date && $to_date) {
            $query->whereBetween(DB::raw('DATE(i.created_at)'), [$from_date, $to_date]);
        } elseif ($from_date) {
            $query->where(DB::raw('DATE(i.created_at)'), '>=', $from_date);
        } elseif ($to_date) {
            $query->where(DB::raw('DATE(i.created_at)'), '<=', $to_date);
        }

        if ($payment_method && $payment_method !== 'all') {
            $query->where('i.payment_method', $payment_method);
        }

        $today = date('Y-m-d');
        if ($payment_status === 'paid') {
            $query->where('i.balance_amount', 0);
        } elseif ($payment_status === 'not_paid') {
            $query->where('i.paid_amount', 0)
                  ->where('i.balance_amount', '>', 0)
                  ->where(function($q) use ($today) {
                      $q->whereNull('i.due_date')->orWhere('i.due_date', '>=', $today);
                  });
        } elseif ($payment_status === 'pending') {
            $query->where('i.paid_amount', '>', 0)
                  ->where('i.balance_amount', '>', 0)
                  ->where(function($q) use ($today) {
                      $q->whereNull('i.due_date')->orWhere('i.due_date', '>=', $today);
                  });
        } elseif ($payment_status === 'overdue') {
            $query->where('i.balance_amount', '>', 0)->where('i.due_date', '<', $today);
        }

        if ($customer_name) {
            $query->where('i.customer_name', 'like', "%{$customer_name}%");
        }

        $invoices = $query->orderBy('i.id', 'desc')->get();

        $rows = [];
        $total_invoices    = 0;
        $total_amount_sum  = 0;
        $total_paid_sum    = 0;
        $total_pending_sum = 0;
        $total_brand_amount_sum = 0;

        foreach ($invoices as $row) {
            $rowArray = (array)$row;

            $bal = floatval($rowArray['balance_amount']);
            $paid = floatval($rowArray['paid_amount']);
            if ($bal == 0) {
                $rowArray['payment_status'] = 'paid';
            } elseif ($paid == 0) {
                if (!empty($rowArray['due_date']) && $rowArray['due_date'] < $today) {
                    $rowArray['payment_status'] = 'overdue';
                } else {
                    $rowArray['payment_status'] = 'not_paid';
                }
            } else {
                if (!empty($rowArray['due_date']) && $rowArray['due_date'] < $today) {
                    $rowArray['payment_status'] = 'overdue';
                } else {
                    $rowArray['payment_status'] = 'pending';
                }
            }

            $products_list = json_decode($rowArray['products'] ?? '[]', true);
            $rowArray['products'] = $products_list;

            $matched = true;
            $rowArray['brand_line_amount'] = 0;

            if ($brand_id > 0) {
                $matched = false;
                foreach ($products_list as $item) {
                    $productId = intval($item['product_id'] ?? 0);
                    $qty   = floatval($item['quantity'] ?? $item['qty'] ?? 0);
                    $price = floatval($item['price'] ?? $item['unit_price'] ?? 0);

                    $prod = Product::find($productId);
                    if ($prod && intval($prod->brand_id) == $brand_id) {
                        $matched = true;
                        $rowArray['brand_line_amount'] += ($qty * $price);
                    }
                }
            }

            if (!$matched) {
                continue;
            }

            $rows[] = $rowArray;
            $total_invoices++;
            $total_amount_sum += floatval($rowArray['total_amount']);
            $total_paid_sum += floatval($rowArray['paid_amount']);
            $total_pending_sum += floatval($rowArray['balance_amount']);
            $total_brand_amount_sum += floatval($rowArray['brand_line_amount']);
        }

        return response()->json([
            "status" => true,
            "data" => $rows,
            "summary" => [
                "total_invoices" => $total_invoices,
                "total_amount" => $total_amount_sum,
                "total_paid" => $total_paid_sum,
                "total_pending" => $total_pending_sum,
                "total_brand_amount" => $total_brand_amount_sum
            ]
        ]);
    }

    public function getFilteredPending(Request $request)
    {
        $company_id     = intval($request->input('company_id', 0));
        $from_date      = $request->input('from_date', '');
        $to_date        = $request->input('to_date', '');
        $payment_method = $request->input('payment_method', 'all');
        $payment_status = $request->input('payment_status', 'all');
        $customer_name  = trim($request->input('customer_name', ''));
        $due_status     = $request->input('due_status', 'all');

        if (!$company_id) {
            return response()->json(["status" => false, "message" => "company_id required"]);
        }

        $query = DB::table('invoices as i')
            ->leftJoin('users as u', 'i.cashier_id', '=', 'u.id')
            ->leftJoin('customers as c', 'i.customer_id', '=', 'c.id')
            ->select(
                'i.*',
                'u.name as cashier_name',
                'c.credit_limit',
                'c.credit_days',
                DB::raw("(SELECT SUM(p.paid_amount) FROM payments p WHERE p.invoice_id = i.id AND p.payment_status = 'paid') as paid_amount_total")
            )
            ->where('i.company_id', $company_id)
            ->where('i.balance_amount', '>', 0)
            ->whereIn('i.payment_status', ['not_paid', 'partial']);

        if ($from_date && $to_date) {
            $query->whereBetween(DB::raw('DATE(i.created_at)'), [$from_date, $to_date]);
        } elseif ($from_date) {
            $query->where(DB::raw('DATE(i.created_at)'), '>=', $from_date);
        } elseif ($to_date) {
            $query->where(DB::raw('DATE(i.created_at)'), '<=', $to_date);
        }

        if ($payment_method && $payment_method !== 'all') {
            $query->where('i.payment_method', $payment_method);
        }

        if ($payment_status && $payment_status !== 'all') {
            $query->where('i.payment_status', $payment_status);
        }

        if ($customer_name) {
            $query->where('i.customer_name', 'like', "%{$customer_name}%");
        }

        $today = date('Y-m-d');
        if ($due_status === 'overdue') {
            $query->whereNotNull('i.due_date')->where('i.due_date', '<', $today);
        } elseif ($due_status === 'upcoming') {
            $query->where(function($q) use ($today) {
                $q->whereNull('i.due_date')->orWhere('i.due_date', '>=', $today);
            });
        }

        $invoices = $query->orderBy(DB::raw("CASE WHEN i.due_date IS NOT NULL AND i.due_date < '$today' THEN 0 ELSE 1 END"))
            ->orderBy('i.due_date', 'asc')
            ->orderBy('i.id', 'desc')
            ->get();

        $rows = [];
        $total_pending_sum  = 0.0;
        $total_overdue_sum  = 0.0;
        $overdue_count      = 0;

        foreach ($invoices as $row) {
            $rowArray = (array)$row;
            $rowArray['products'] = json_decode($rowArray['products'] ?? '[]');

            $isOverdue = false;
            if ($rowArray['due_date'] && $rowArray['due_date'] < $today) {
                $isOverdue = true;
            }
            $rowArray['is_overdue'] = $isOverdue;

            if ($rowArray['paid_amount_total'] === null) {
                $rowArray['paid_amount_total'] = $rowArray['paid_amount'];
            }

            $rows[] = $rowArray;
            $total_pending_sum  += floatval($rowArray['balance_amount']);
            if ($isOverdue) {
                $total_overdue_sum += floatval($rowArray['balance_amount']);
                $overdue_count++;
            }
        }

        return response()->json([
            "status"  => true,
            "data"    => $rows,
            "summary" => [
                "total_pending"  => $total_pending_sum,
                "total_overdue"  => $total_overdue_sum,
                "overdue_count"  => $overdue_count,
                "total_records"  => count($rows),
            ]
        ]);
    }

    // public function getInvoiceById(Request $request)
    // {
    //     $idVal = $request->input('id') ?: $request->query('id', '');
    //     if (is_numeric($idVal)) {
    //         $invoice = Invoice::find(intval($idVal));
    //     } else {
    //         $invoice = Invoice::where('invoice_no', $idVal)->first();
    //     }
        
    //     if (!$invoice) {
    //         $invoice = Invoice::where('id', intval($idVal))->first();
    //     }

    //     if (!$invoice) {
    //         return response()->json(["status" => false, "message" => "Invoice not found"]);
    //     }

    //     $data = $invoice->toArray();
    //     if (is_string($data['products'])) {
    //         $data['products'] = json_decode($data['products']);
    //     }

    //     return response()->json(["status" => true, "data" => $data]);
    // }

    public function getInvoiceById(Request $request)
{
    $idVal = $request->input('id') ?: $request->query('id', '');

    $query = DB::table('invoices as i')
        ->leftJoin('companies as c', 'i.company_id', '=', 'c.id')
        ->leftJoin('users as u', 'i.cashier_id', '=', 'u.id')
        ->select(
            'i.*',
            'c.company_name',
            'c.company_address',
            'c.phone',
            'c.gstin',
            'c.logo',
            'u.name as cashier_name'
        );

    if (is_numeric($idVal)) {
        $invoice = (clone $query)->where('i.id', intval($idVal))->first();
    } else {
        $invoice = (clone $query)->where('i.invoice_no', $idVal)->first();
    }

    if (!$invoice) {
        return response()->json(["status" => false, "message" => "Invoice not found"]);
    }

    $data = (array) $invoice;
    if (is_string($data['products'])) {
        $data['products'] = json_decode($data['products']);
    }

    return response()->json(["status" => true, "data" => $data]);
}

    public function getPendingInvoice(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));

        if (!$company_id) {
            return response()->json(["status" => false, "message" => "company_id required"]);
        }

        $invoices = DB::table('invoices as i')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->select('i.*', DB::raw("IFNULL(c.credit_limit,0) as credit_limit"), DB::raw("(i.total_amount - i.balance_amount) as paid_amount_total"))
            ->where('i.company_id', $company_id)
            ->where('i.balance_amount', '>', 0)
            ->orderBy('i.id', 'desc')
            ->get();

        $rows = [];
        foreach ($invoices as $row) {
            $rowArray = (array)$row;
            $rowArray['products'] = json_decode($rowArray['products']);
            $rows[] = $rowArray;
        }

        return response()->json(["status" => true, "data" => $rows]);
    }

    public function getPendingInvoiceHistory(Request $request)
    {
        $admin_id = intval($request->input('admin_id') ?: $request->query('admin_id', 0));

        if (!$admin_id) {
            return response()->json(["status" => false, "message" => "admin_id required"]);
        }

        $invoices = DB::table('invoices as i')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->select('i.*', DB::raw("IFNULL(c.credit_limit, 0) as credit_limit"), DB::raw("(i.total_amount - i.balance_amount) as paid_amount_total"))
            ->where('c.admin_id', $admin_id)
            ->where('c.is_deleted', 0)
            ->orderBy('i.id', 'desc')
            ->get();

        $rows = [];
        foreach ($invoices as $row) {
            $rowArray = (array)$row;
            $rowArray['products'] = json_decode($rowArray['products'] ?? '[]');

            $balance = floatval($rowArray['balance_amount']);
            $paid = floatval($rowArray['paid_amount_total']);

            if ($balance <= 0) {
                $rowArray['payment_history_status'] = "paid";
            } elseif ($paid > 0) {
                $rowArray['payment_history_status'] = "partial";
            } else {
                $rowArray['payment_history_status'] = "pending";
            }

            $rows[] = $rowArray;
        }

        return response()->json(["status" => true, "data" => $rows]);
    }

    public function markAsPaid(Request $request)
    {
        $invoice_no = $request->input('invoice_no', '');
        $pay_amount = floatval($request->input('pay_amount', 0));

        if (!$invoice_no || $pay_amount <= 0) {
            return response()->json(["status" => false, "message" => "Invalid data"]);
        }

        $invoice = Invoice::where('invoice_no', $invoice_no)->first();
        if (!$invoice) {
            return response()->json(["status" => false, "message" => "Invoice not found"]);
        }

        $current_paid = floatval($invoice->paid_amount);
        $total_amount = floatval($invoice->total_amount);

        $new_paid = $current_paid + $pay_amount;
        $return_amount = 0;

        if ($new_paid > $total_amount) {
            $return_amount = $new_paid - $total_amount;
            $new_paid = $total_amount;
        }

        $new_balance = $total_amount - $new_paid;
        $payment_status = $new_balance <= 0 ? "paid" : "partial";

        DB::beginTransaction();
        try {
            $invoice->update([
                'paid_amount' => $new_paid,
                'balance_amount' => $new_balance,
                'payment_status' => $payment_status
            ]);

            Payment::where('invoice_no', $invoice_no)->update([
                'paid_amount' => $new_paid,
                'balance_amount' => $new_balance,
                'payment_status' => $payment_status
            ]);

            DB::commit();

            $message = "Payment Updated Successfully";
            if ($return_amount > 0) {
                $message .= " | Return Amount: ₹" . number_format($return_amount);
            }

            return response()->json([
                "status" => true,
                "message" => $message,
                "return_amount" => $return_amount,
                "paid_amount" => $new_paid,
                "balance_amount" => $new_balance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["status" => false, "message" => $e->getMessage()]);
        }
    }

    public function payment(Request $request)
    {
        $company_id     = intval($request->input('company_id', 0));
        $invoice_id     = intval($request->input('invoice_id', 0));
        $invoice_no     = $request->input('invoice_no', '');
        $customer_id    = intval($request->input('customer_id', 0));
        $total_amount   = floatval($request->input('total_amount', 0));
        $paid_amount    = floatval($request->input('paid_amount', 0));
        $balance_amount = floatval($request->input('balance_amount', 0));
        $payment_method = $request->input('payment_method', 'cash');
        $payment_status = $request->input('payment_status', 'paid');

        if ($payment_method === 'credit') {
            $payment_status = 'not_paid';
            $paid_amount    = 0;
            $balance_amount = $total_amount;
        } else {
            if ($paid_amount >= $total_amount) {
                $payment_status = 'paid';
                $balance_amount = 0;
            } else {
                $payment_status = 'partial';
                $balance_amount = $total_amount - $paid_amount;
            }
        }

        try {
            $payment = Payment::create([
                'company_id' => $company_id,
                'invoice_id' => $invoice_id,
                'invoice_no' => $invoice_no,
                'customer_id' => $customer_id,
                'total_amount' => $total_amount,
                'paid_amount' => $paid_amount,
                'balance_amount' => $balance_amount,
                'payment_method' => $payment_method,
                'payment_status' => $payment_status,
                'notes' => ''
            ]);

            return response()->json([
                "status"     => true,
                "invoice_no" => $invoice_no,
                "invoice_id" => $invoice_id,
                "payment_id" => $payment->id,
                "payment_status" => $payment_status,
            ]);
        } catch (\Exception $e) {
            return response()->json(["status" => false, "message" => $e->getMessage()], 500);
        }
    }

    public function updateCreditPayment(Request $request)
    {
        $invoice_id = intval($request->input('invoice_id', 0));
        $amount = floatval($request->input('amount', 0));
        $payment_method = $request->input('payment_method', 'cash');

        if ($invoice_id <= 0) {
            return response()->json(["status" => false, "message" => "Invalid Invoice ID"], 400);
        }
        if ($amount <= 0) {
            return response()->json(["status" => false, "message" => "Enter Valid Amount"], 400);
        }

        $invoice = Invoice::find($invoice_id);
        if (!$invoice) {
            return response()->json(["status" => false, "message" => "Invoice Not Found"], 404);
        }

        $current_paid = floatval($invoice->paid_amount);
        $current_balance = floatval($invoice->balance_amount);

        $new_paid = $current_paid + $amount;
        $new_balance = max(0.0, $current_balance - $amount);
        $payment_status = $new_balance <= 0 ? 'paid' : 'partial';

        DB::beginTransaction();
        try {
            $invoice->update([
                'paid_amount' => $new_paid,
                'balance_amount' => $new_balance,
                'payment_method' => $payment_method,
                'payment_status' => $payment_status
            ]);

            Payment::where('invoice_no', $invoice->invoice_no)->update([
                'paid_amount' => $new_paid,
                'balance_amount' => $new_balance,
                'payment_method' => $payment_method,
                'payment_status' => $payment_status
            ]);

            DB::commit();
            return response()->json([
                "status" => true,
                "message" => "Payment Updated Successfully"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["status" => false, "message" => $e->getMessage()], 500);
        }
    }

    public function verifyGst(Request $request)
    {
        $gstin = trim($request->input('gst_no', ''));
        if ($gstin == '') {
            return response()->json(["status" => false, "message" => "GST Number required"]);
        }

        $apiKey = "key_live_7157232fd01340bab4657b0bbc90dbb4";
        $apiSecret = "secret_live_aabc1f041b3b4544b99b6feab57faeae";

        $authResponse = Http::withHeaders([
            "x-api-key" => $apiKey,
            "x-api-secret" => $apiSecret,
            "x-api-version" => "1.0.0"
        ])->post("https://api.sandbox.co.in/authenticate");

        if ($authResponse->failed()) {
            return response()->json([
                "status" => false,
                "step" => "auth",
                "response" => $authResponse->json()
            ]);
        }

        $authData = $authResponse->json();
        $accessToken = $authData['access_token'] ?? $authData['data']['access_token'] ?? '';

        if (!$accessToken) {
            return response()->json([
                "status" => false,
                "step" => "auth_token_extract",
                "response" => $authData
            ]);
        }

        $gstResponse = Http::withHeaders([
            "authorization" => $accessToken,
            "x-api-key" => $apiKey,
            "Content-Type" => "application/json"
        ])->post("https://api.sandbox.co.in/gst/compliance/public/gstin/search", [
            "gstin" => $gstin
        ]);

        if ($gstResponse->successful()) {
            $gstData = $gstResponse->json();
            return response()->json([
                "status" => true,
                "business_name" => $gstData['data']['business_name'] ?? "",
                "data" => $gstData['data']
            ]);
        } else {
            return response()->json([
                "status" => false,
                "message" => "Invalid GST Number",
                "response" => $gstResponse->json()
            ]);
        }
    }

    public function payCustomerBulk(Request $request)
    {
        $company_id     = intval($request->input('company_id', 0));
        $customer_id    = intval($request->input('customer_id', 0));
        $total_amount   = floatval($request->input('amount', 0));
        $payment_method = $request->input('payment_method', 'cash');
        $payment_date   = $request->input('payment_date', date('Y-m-d'));
        $notes          = $request->input('notes', '');

        if ($customer_id <= 0) {
            return response()->json(['status' => false, 'message' => 'Invalid Customer ID'], 400);
        }
        if ($total_amount <= 0) {
            return response()->json(['status' => false, 'message' => 'Payment amount must be greater than 0'], 400);
        }

        // Fetch all pending invoices for this customer, oldest first (FIFO)
        $query = Invoice::where('customer_id', $customer_id)
            ->where('balance_amount', '>', 0);
            
        if ($company_id > 0) {
            $query->where('company_id', $company_id);
        }

        $pendingInvoices = $query->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $cust = Customer::find($customer_id);
        if (!$cust) {
            return response()->json(['status' => false, 'message' => 'Customer not found'], 404);
        }

        $remaining = $total_amount;
        $applied   = [];

        DB::beginTransaction();
        try {
            if ($pendingInvoices->isNotEmpty()) {
                foreach ($pendingInvoices as $invoice) {
                    if ($remaining <= 0) break;

                    $balance  = floatval($invoice->balance_amount);
                    $applying = min($remaining, $balance);
                    $remaining -= $applying;

                    $new_paid    = floatval($invoice->paid_amount) + $applying;
                    $new_balance = max(0.00, $balance - $applying);
                    $payment_status = $new_balance <= 0 ? 'paid' : 'partial';

                    $invoice->update([
                        'paid_amount'    => $new_paid,
                        'balance_amount' => $new_balance,
                        'payment_method' => $payment_method,
                        'payment_status' => $payment_status
                    ]);

                    Payment::where('invoice_no', $invoice->invoice_no)->update([
                        'paid_amount'    => $new_paid,
                        'balance_amount' => $new_balance,
                        'payment_method' => $payment_method,
                        'payment_status' => $payment_status,
                        'notes'          => $notes
                    ]);

                    $applied[] = [
                        'invoice_id'   => $invoice->id,
                        'invoice_no'   => $invoice->invoice_no,
                        'applied'      => $applying,
                        'new_balance'  => $new_balance
                    ];
                }
            }

            // Update customer pending amount and store any excess in advance_balance
            $distributed = $total_amount - $remaining;
            $cust->pending_amount = max(0.00, floatval($cust->pending_amount) - $distributed);
            
            if ($remaining > 0) {
                $cust->advance_balance = floatval($cust->advance_balance) + $remaining;
            }
            $cust->save();

            DB::commit();

            return response()->json([
                'status'   => true,
                'message'  => 'Bulk payment recorded successfully. Distributed: ₹' . number_format($distributed, 2) . ($remaining > 0 ? ', Leftover stored as advance: ₹' . number_format($remaining, 2) : ''),
                'applied'  => $applied,
                'leftover' => max(0.0, $remaining)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Error recording bulk payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCustomerPayments(Request $request)
    {
        $customerId = intval($request->query('customer_id', 0));
        if ($customerId <= 0) {
            return response()->json(["status" => false, "message" => "Customer ID required"]);
        }

        $payments = DB::table('payments as p')
            ->leftJoin('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->select('p.*', DB::raw('p.paid_amount as amount'), DB::raw('DATE(i.created_at) as invoice_date'), DB::raw('DATE(p.updated_at) as payment_date'))
            ->where('p.customer_id', $customerId)
            ->where('p.paid_amount', '>', 0)
            ->orderBy('p.updated_at', 'desc')
            ->get();

        return response()->json(["status" => true, "data" => $payments]);
    }
}
