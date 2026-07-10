<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Brand;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    /**
     * Parse and validate imported Excel/JSON items.
     * Looks up existing Category/Subcategory/Brand/Product mappings.
     */
    public function validateItems(Request $request)
    {
        $companyId = intval($request->input('company_id', 0));
        $items = $request->input('items', []);

        if (!$companyId) {
            return response()->json([
                'status' => false,
                'message' => 'Company ID is required'
            ]);
        }

        $validatedItems = [];

        foreach ($items as $item) {
            $productName = trim($item['product_name'] ?? '');
            $productCode = trim($item['product_code'] ?? '');
            $barcode = trim($item['barcode'] ?? '');
            $categoryName = trim($item['category_name'] ?? '');
            $subcategoryName = trim($item['subcategory_name'] ?? '');
            $brandName = trim($item['brand_name'] ?? '');
            $price = floatval($item['price'] ?? 0);
            $quantity = intval($item['quantity'] ?? 0);
            $unit = trim($item['unit'] ?? '');
            $gstPercentage = floatval($item['gst_percentage'] ?? 0);

            $productId = null;
            $categoryId = null;
            $subcategoryId = null;
            $brandId = null;

            $status = 'valid';
            $errors = [];
            $warnings = [];

            if (empty($productName)) {
                $status = 'error';
                $errors[] = 'Product name is required';
            }

            if ($quantity <= 0) {
                $status = 'error';
                $errors[] = 'Quantity must be greater than 0';
            }

            // 1. Try to find existing product by Code, Barcode, or Name
            $productQuery = Product::where('company_id', $companyId)->where('is_deleted', 0);
            $existingProduct = null;

            if (!empty($productCode)) {
                $existingProduct = (clone $productQuery)->where('product_code', $productCode)->first();
            }
            if (!$existingProduct && !empty($barcode)) {
                $existingProduct = (clone $productQuery)->where('barcode', $barcode)->first();
            }
            if (!$existingProduct && !empty($productName)) {
                $existingProduct = (clone $productQuery)->whereRaw('LOWER(product_name) = ?', [strtolower($productName)])->first();
            }

            if ($existingProduct) {
                $productId = $existingProduct->id;
                $categoryId = $existingProduct->category_id;
                $subcategoryId = $existingProduct->subcategory_id;
                $brandId = $existingProduct->brand_id;
                $warnings[] = 'Product already exists. Stock will be incremented.';
            } else {
                // 2. Resolve Category
                if (!empty($categoryName)) {
                    $category = Category::where('company_id', $companyId)
                        ->where('is_deleted', 0)
                        ->whereRaw('LOWER(name) = ?', [strtolower($categoryName)])
                        ->first();
                    if ($category) {
                        $categoryId = $category->id;
                    } else {
                        $warnings[] = "Category '{$categoryName}' will be created.";
                    }
                }

                // 3. Resolve Subcategory
                if (!empty($subcategoryName) && $categoryId) {
                    $subcategory = Subcategory::where('company_id', $companyId)
                        ->where('category_id', $categoryId)
                        ->where('is_deleted', 0)
                        ->whereRaw('LOWER(name) = ?', [strtolower($subcategoryName)])
                        ->first();
                    if ($subcategory) {
                        $subcategoryId = $subcategory->id;
                    } else {
                        $warnings[] = "Subcategory '{$subcategoryName}' will be created.";
                    }
                } elseif (!empty($subcategoryName) && !$categoryId) {
                    $warnings[] = "Subcategory '{$subcategoryName}' will be created under new Category.";
                }

                // 4. Resolve Brand
                if (!empty($brandName)) {
                    $brand = Brand::where('company_id', $companyId)
                        ->where('is_deleted', 0)
                        ->whereRaw('LOWER(name) = ?', [strtolower($brandName)])
                        ->first();
                    if ($brand) {
                        $brandId = $brand->id;
                    } else {
                        $warnings[] = "Brand '{$brandName}' will be created.";
                    }
                }
            }

            if (count($errors) > 0) {
                $status = 'error';
            } elseif (count($warnings) > 0) {
                $status = 'warning';
            }

            $validatedItems[] = [
                'product_name' => $productName,
                'product_code' => $productCode ?: null,
                'barcode' => $barcode ?: null,
                'category_name' => $categoryName,
                'subcategory_name' => $subcategoryName,
                'brand_name' => $brandName,
                'price' => $price,
                'quantity' => $quantity,
                'unit' => $unit ?: 'pcs',
                'gst_percentage' => $gstPercentage,
                'product_id' => $productId,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'brand_id' => $brandId,
                'status' => $status,
                'errors' => $errors,
                'warnings' => $warnings
            ];
        }

        return response()->json([
            'status' => true,
            'data' => $validatedItems
        ]);
    }

    /**
     * Save purchase as draft.
     */
    public function saveDraft(Request $request)
    {
        $id = intval($request->input('id', 0));
        $companyId = intval($request->input('company_id', 0));
        $supplierId = intval($request->input('supplier_id', 0));
        $purchaseNo = trim($request->input('purchase_no', ''));
        $purchaseDate = $request->input('purchase_date', date('Y-m-d'));
        $items = $request->input('items', []);

        if (!$companyId || !$supplierId) {
            return response()->json([
                'status' => false,
                'message' => 'Company and Supplier are required'
            ]);
        }

        DB::beginTransaction();
        try {
            // Calculated totals
            $subTotal = 0;
            $gstTotal = 0;
            $totalAmount = 0;

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $gstPct = floatval($item['gst_percentage'] ?? 0);

                $itemSub = $qty * $price;
                $itemGst = $itemSub * ($gstPct / 100);

                $subTotal += $itemSub;
                $gstTotal += $itemGst;
                $totalAmount += ($itemSub + $itemGst);
            }

            $purchaseData = [
                'purchase_no' => $purchaseNo ?: null,
                'supplier_id' => $supplierId,
                'company_id' => $companyId,
                'purchase_date' => $purchaseDate,
                'sub_total' => $subTotal,
                'gst_total' => $gstTotal,
                'total_amount' => $totalAmount,
                'paid_amount' => floatval($request->input('paid_amount', 0)),
                'balance_amount' => $totalAmount - floatval($request->input('paid_amount', 0)),
                'status' => 'draft'
            ];

            if ($id > 0) {
                $purchase = Purchase::where('id', $id)->where('company_id', $companyId)->first();
                if (!$purchase) {
                    return response()->json(['status' => false, 'message' => 'Purchase not found']);
                }
                $purchase->update($purchaseData);
                // Clear old items
                PurchaseItem::where('purchase_id', $purchase->id)->delete();
            } else {
                $purchase = Purchase::create($purchaseData);
            }

            foreach ($items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => trim($item['product_name'] ?? ''),
                    'product_code' => trim($item['product_code'] ?? '') ?: null,
                    'barcode' => trim($item['barcode'] ?? '') ?: null,
                    'category_name' => trim($item['category_name'] ?? '') ?: null,
                    'subcategory_name' => trim($item['subcategory_name'] ?? '') ?: null,
                    'brand_name' => trim($item['brand_name'] ?? '') ?: null,
                    'category_id' => $item['category_id'] ?? null,
                    'subcategory_id' => $item['subcategory_id'] ?? null,
                    'brand_id' => $item['brand_id'] ?? null,
                    'price' => floatval($item['price'] ?? 0),
                    'quantity' => intval($item['quantity'] ?? 0),
                    'unit' => trim($item['unit'] ?? 'pcs'),
                    'gst_percentage' => floatval($item['gst_percentage'] ?? 0),
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Purchase saved as draft successfully',
                'purchase_id' => $purchase->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error saving draft: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Submit purchase and commit stocks to Inventory.
     */
    public function submitPurchase(Request $request)
    {
        $id = intval($request->input('id', 0));
        $companyId = intval($request->input('company_id', 0));
        $supplierId = intval($request->input('supplier_id', 0));
        $purchaseNo = trim($request->input('purchase_no', ''));
        $purchaseDate = $request->input('purchase_date', date('Y-m-d'));
        $items = $request->input('items', []);

        if (!$companyId || !$supplierId) {
            return response()->json([
                'status' => false,
                'message' => 'Company and Supplier are required'
            ]);
        }

        DB::beginTransaction();
        try {
            $subTotal = 0;
            $gstTotal = 0;
            $totalAmount = 0;

            // 1. Calculate totals
            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $gstPct = floatval($item['gst_percentage'] ?? 0);

                $itemSub = $qty * $price;
                $itemGst = $itemSub * ($gstPct / 100);

                $subTotal += $itemSub;
                $gstTotal += $itemGst;
                $totalAmount += ($itemSub + $itemGst);
            }

            $purchaseData = [
                'purchase_no' => $purchaseNo ?: null,
                'supplier_id' => $supplierId,
                'company_id' => $companyId,
                'purchase_date' => $purchaseDate,
                'sub_total' => $subTotal,
                'gst_total' => $gstTotal,
                'total_amount' => $totalAmount,
                'paid_amount' => floatval($request->input('paid_amount', 0)),
                'balance_amount' => $totalAmount - floatval($request->input('paid_amount', 0)),
                'status' => 'submitted'
            ];

            if ($id > 0) {
                $purchase = Purchase::where('id', $id)->where('company_id', $companyId)->first();
                if (!$purchase) {
                    return response()->json(['status' => false, 'message' => 'Purchase not found']);
                }
                // If it was already submitted, don't allow duplicate inventory updates
                if ($purchase->status === 'submitted') {
                    return response()->json(['status' => false, 'message' => 'Purchase already finalized']);
                }
                $purchase->update($purchaseData);
                PurchaseItem::where('purchase_id', $purchase->id)->delete();
            } else {
                $purchase = Purchase::create($purchaseData);
            }

            // 2. Process and create items & products
            foreach ($items as $item) {
                $productName = trim($item['product_name'] ?? '');
                $productCode = trim($item['product_code'] ?? '') ?: null;
                $barcode = trim($item['barcode'] ?? '') ?: null;
                $categoryName = trim($item['category_name'] ?? '');
                $subcategoryName = trim($item['subcategory_name'] ?? '');
                $brandName = trim($item['brand_name'] ?? '');
                $price = floatval($item['price'] ?? 0);
                $qty = intval($item['quantity'] ?? 0);
                $unit = trim($item['unit'] ?? 'pcs');
                $gstPct = floatval($item['gst_percentage'] ?? 0);

                $productId = $item['product_id'] ?? null;
                $categoryId = $item['category_id'] ?? null;
                $subcategoryId = $item['subcategory_id'] ?? null;
                $brandId = $item['brand_id'] ?? null;

                // Create Categories / Subcategories / Brands dynamically if missing
                if (!$productId) {
                    if (!$categoryId && !empty($categoryName)) {
                        $cat = Category::firstOrCreate(
                            ['company_id' => $companyId, 'name' => $categoryName, 'is_deleted' => 0],
                            ['status' => 'active']
                        );
                        $categoryId = $cat->id;
                    }

                    if (!$subcategoryId && !empty($subcategoryName) && $categoryId) {
                        $sub = Subcategory::firstOrCreate(
                            ['company_id' => $companyId, 'category_id' => $categoryId, 'name' => $subcategoryName, 'is_deleted' => 0],
                            ['status' => 'active']
                        );
                        $subcategoryId = $sub->id;
                    }

                    if (!$brandId && !empty($brandName)) {
                        // Brand requires category/subcategory IDs in our table schema
                        $brand = Brand::firstOrCreate(
                            ['company_id' => $companyId, 'name' => $brandName, 'is_deleted' => 0],
                            [
                                'category_id' => $categoryId ?: 0,
                                'subcategory_id' => $subcategoryId ?: 0,
                                'status' => 'active'
                            ]
                        );
                        $brandId = $brand->id;
                    }

                    // Look up if a product with same name/code exists before creating
                    $existingProd = Product::where('company_id', $companyId)
                        ->where('is_deleted', 0)
                        ->where(function($q) use ($productName, $productCode, $barcode) {
                            $q->whereRaw('LOWER(product_name) = ?', [strtolower($productName)]);
                            if ($productCode) $q->orWhere('product_code', $productCode);
                            if ($barcode) $q->orWhere('barcode', $barcode);
                        })->first();

                    if ($existingProd) {
                        $productId = $existingProd->id;
                    } else {
                        // Create Brand new product catalog item
                        $newProd = Product::create([
                            'product_name' => $productName,
                            'product_code' => $productCode ?: ('PRD' . rand(100000, 999999)),
                            'barcode' => $barcode ?: null,
                            'category_id' => $categoryId,
                            'subcategory_id' => $subcategoryId,
                            'brand_id' => $brandId,
                            'price' => $price, // Cost price
                            'stock' => 0, // Stock starts at 0, incremented below
                            'unit' => $unit,
                            'gst_percentage' => $gstPct,
                            'company_id' => $companyId,
                            'supplier_id' => $supplierId,
                            'status' => 'active',
                            'is_deleted' => 0
                        ]);
                        $productId = $newProd->id;
                    }
                }

                // Increments the catalog stock
                if ($productId) {
                    $prod = Product::find($productId);
                    if ($prod) {
                        $prod->increment('stock', $qty);
                        // Optional: update product cost price to latest invoice price
                        $prod->update(['price' => $price]);
                    }
                }

                // Save Purchase Item record
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'product_code' => $productCode,
                    'barcode' => $barcode,
                    'category_name' => $categoryName,
                    'subcategory_name' => $subcategoryName,
                    'brand_name' => $brandName,
                    'category_id' => $categoryId,
                    'subcategory_id' => $subcategoryId,
                    'brand_id' => $brandId,
                    'price' => $price,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'gst_percentage' => $gstPct,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Purchase submitted successfully. Stocks updated.',
                'purchase_id' => $purchase->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error finalizing purchase: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get list of purchases.
     */
    public function getPurchases(Request $request)
    {
        $companyId = intval($request->input('company_id') ?: $request->query('company_id', 0));
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $supplierId = intval($request->query('supplier_id', 0));
        $status = $request->query('status');

        if (!$companyId) {
            return response()->json([
                'status' => true,
                'data' => []
            ]);
        }

        $query = DB::table('purchases as p')
            ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.id')
            ->select('p.*', 's.supplier_name', 's.gst_number as supplier_gstin')
            ->where('p.company_id', $companyId);

        if (!empty($startDate)) {
            $query->whereDate('p.purchase_date', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('p.purchase_date', '<=', $endDate);
        }
        if ($supplierId > 0) {
            $query->where('p.supplier_id', $supplierId);
        }
        if (!empty($status)) {
            $query->where('p.status', $status);
        }

        $purchases = $query->orderBy('p.id', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $purchases
        ]);
    }

    /**
     * Get purchase details by ID.
     */
    public function getPurchaseById(Request $request)
    {
        $id = intval($request->input('id') ?: $request->query('id', 0));
        $purchase = Purchase::with(['supplier', 'items'])->find($id);

        if (!$purchase) {
            return response()->json([
                'status' => false,
                'message' => 'Purchase record not found'
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $purchase
        ]);
    }

    /**
     * Delete draft purchase.
     */
    public function deletePurchase(Request $request)
    {
        $id = intval($request->input('id', 0));
        $purchase = Purchase::find($id);

        if (!$purchase) {
            return response()->json(['status' => false, 'message' => 'Purchase not found']);
        }

        if ($purchase->status === 'submitted') {
            return response()->json(['status' => false, 'message' => 'Cannot delete a submitted purchase invoice']);
        }

        $purchase->delete();

        return response()->json([
            'status' => true,
            'message' => 'Purchase draft deleted successfully'
        ]);
    }
}
