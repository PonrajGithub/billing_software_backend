<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));

        if (!$company_id) {
            return response()->json([
                "status" => false,
                "message" => "company_id required"
            ]);
        }

        $sales = Invoice::where('company_id', $company_id)->sum('total_amount');
        $totalProducts = Product::where('is_deleted', 0)
            ->where('company_id', $company_id)
            ->where('status', 'active')
            ->count();

        $lowStock = Product::where('stock', '<', 5)
            ->where('is_deleted', 0)
            ->where('company_id', $company_id)
            ->count();

        return response()->json([
            "status" => true,
            "data" => [
                "total_sales" => floatval($sales),
                "total_products" => intval($totalProducts),
                "low_stock" => intval($lowStock)
            ]
        ]);
    }

    public function getAnalytics(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));

        if (!$company_id) {
            return response()->json([
                "status" => false,
                "message" => "company_id required"
            ]);
        }

        $months = [
            1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"May", 6=>"Jun",
            7=>"Jul", 8=>"Aug", 9=>"Sep", 10=>"Oct", 11=>"Nov", 12=>"Dec"
        ];

        $data = array_fill(1, 12, 0);

        $sales = Invoice::where('company_id', $company_id)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('SUM(total_amount) as total'))
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->get();

        foreach ($sales as $row) {
            $data[intval($row->month)] = floatval($row->total);
        }

        $monthly = [];
        foreach ($data as $m => $val) {
            $monthly[] = [
                "month" => $months[$m],
                "total" => floatval($val)
            ];
        }

        return response()->json([
            "status" => true,
            "data" => [
                "monthly_sales" => $monthly
            ]
        ]);
    }

    public function getDashboard(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));

        if (!$company_id) {
            return response()->json(["status" => false, "message" => "Company ID required"]);
        }

        // 1. TOTAL CREDIT SALES
        $credit = floatval(Invoice::where('company_id', $company_id)
            ->where('payment_type', 'credit')
            ->sum('total_amount'));

        // 2. TOTAL OUTSTANDING
        $outstanding = floatval(Payment::where('company_id', $company_id)
            ->where('balance_amount', '>', 0)
            ->sum('balance_amount'));

        // 3. OVERDUE AMOUNT
        $overdue = floatval(DB::table('payments as p')
            ->join('invoices as i', 'p.invoice_id', '=', 'i.id')
            ->where('p.company_id', $company_id)
            ->where('p.balance_amount', '>', 0)
            ->whereNotNull('i.due_date')
            ->where('i.due_date', '<', now()->toDateString())
            ->sum('p.balance_amount'));

        // 4. TODAY COLLECTION
        $today = floatval(Payment::where('company_id', $company_id)
            ->whereDate('created_at', now()->toDateString())
            ->sum('paid_amount'));

        // 5. TABLE DATA
        $list = [];
        $records = DB::table('payments as p')
            ->join('invoices as i', 'p.invoice_id', '=', 'i.id')
            ->where('p.company_id', $company_id)
            ->where('p.balance_amount', '>', 0)
            ->select('i.customer_name', 'p.balance_amount', 'i.due_date')
            ->orderBy('i.due_date', 'asc')
            ->get();

        foreach ($records as $row) {
            $status = 'Pending';
            if (floatval($row->balance_amount) <= 0) {
                $status = 'Paid';
            } elseif ($row->due_date && $row->due_date < now()->toDateString()) {
                $status = 'Overdue';
            }

            $list[] = [
                "customer"    => $row->customer_name,
                "outstanding" => floatval($row->balance_amount),
                "due_date"    => $row->due_date,
                "status"      => $status
            ];
        }

        return response()->json([
            "status" => true,
            "cards" => [
                "total_credit_sales" => $credit,
                "total_outstanding"  => $outstanding,
                "overdue_amount"     => $overdue,
                "today_collection"   => $today
            ],
            "list" => $list
        ]);
    }

    public function getAdminOverdueNotifications(Request $request)
    {
        $admin_id = intval($request->input('admin_id') ?: $request->query('admin_id', 0));

        if (!$admin_id) {
            return response()->json([
                "status" => false,
                "message" => "Admin id required"
            ]);
        }

        $list = DB::table('invoices as i')
            ->join('companies as c', 'c.id', '=', 'i.company_id')
            ->join('customers as cu', 'cu.id', '=', 'i.customer_id')
            ->where('c.admin_id', $admin_id)
            ->where('i.balance_amount', '>', 0)
            ->where('i.due_date', '<', now()->toDateString())
            ->select('c.company_name', 'cu.id as customer_id', 'cu.name as customer', 'i.invoice_no', 'i.balance_amount as outstanding', 'i.due_date')
            ->orderBy('i.due_date', 'asc')
            ->get();

        return response()->json([
            "status" => true,
            "count" => count($list),
            "data" => $list
        ]);
    }

    public function getUnsoldProductsNotification(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));

        if (!$company_id) {
            return response()->json([
                "status" => false,
                "message" => "Company ID required"
            ]);
        }

        $activeProducts = Product::where('company_id', $company_id)
            ->where('status', 'active')
            ->where('is_deleted', 0)
            ->get();

        $products = [];
        foreach ($activeProducts as $p) {
            $products[$p->id] = [
                "id" => $p->id,
                "product_name" => $p->product_name,
                "created_at" => $p->created_at ? $p->created_at->toDateTimeString() : now()->toDateTimeString(),
                "last_sale" => null
            ];
        }

        $invoices = Invoice::where('company_id', $company_id)->get();

        foreach ($invoices as $invoice) {
            $items = $invoice->products;
            if (!is_array($items)) {
                $items = is_string($items) ? json_decode($items, true) : [];
            }

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $pid = intval($item["product_id"] ?? 0);
                if (isset($products[$pid])) {
                    $invoice_created = $invoice->created_at ? $invoice->created_at->toDateTimeString() : now()->toDateTimeString();
                    if (
                        $products[$pid]["last_sale"] == null ||
                        strtotime($invoice_created) > strtotime($products[$pid]["last_sale"])
                    ) {
                        $products[$pid]["last_sale"] = $invoice_created;
                    }
                }
            }
        }

        $result = [];
        $now = time();
        foreach ($products as $p) {
            if ($p["last_sale"] == null) {
                $days = floor(($now - strtotime($p["created_at"])) / (60*60*24));
                if ($days >= 2) {
                    $result[] = [
                        "product_name" => $p["product_name"],
                        "last_sale" => "Never Billed",
                        "days" => $days
                    ];
                }
            }
        }

        return response()->json([
            "status" => true,
            "count" => count($result),
            "data" => $result
        ]);
    }
}
