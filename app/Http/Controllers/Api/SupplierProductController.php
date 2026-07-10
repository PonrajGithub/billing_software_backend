<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;

class SupplierProductController extends Controller
{
    public function add(Request $request)
    {
        $supplier_id    = intval($request->input('supplier_id', 0));
        $product_name   = trim($request->input('product_name', ''));
        $product_code   = trim($request->input('product_code', ''));
        $category_id    = intval($request->input('category_id', 0));
        $subcategory_id = intval($request->input('subcategory_id', 0));
        $brand_id       = intval($request->input('brand_id', 0));
        $company_id     = intval($request->input('company_id', 0));
        $price          = floatval($request->input('price', 0));
        $stock          = intval($request->input('stock', 0));
        $gst_percentage = floatval($request->input('gst_percentage', 0));
        $barcode        = trim($request->input('barcode', ''));
        $unit           = trim($request->input('unit', ''));

        if (!$supplier_id || !$product_name || !$company_id) {
            return response()->json([
                "status" => false,
                "message" => "Supplier ID, Product Name, and Company ID are required"
            ]);
        }

        SupplierProduct::create([
            'supplier_id' => $supplier_id,
            'product_name' => $product_name,
            'product_code' => $product_code ?: null,
            'category_id' => $category_id,
            'subcategory_id' => $subcategory_id,
            'brand_id' => $brand_id,
            'company_id' => $company_id,
            'price' => $price,
            'stock' => $stock,
            'gst_percentage' => $gst_percentage,
            'barcode' => $barcode ?: null,
            'unit' => $unit
        ]);

        return response()->json([
            "status" => true,
            "message" => "Supplier product added successfully"
        ]);
    }

    public function getById(Request $request)
    {
        $id = intval($request->input('id') ?: $request->query('id', 0));
        $product = SupplierProduct::find($id);

        if (!$product) {
            return response()->json([
                "status" => false,
                "message" => "Product not found"
            ]);
        }

        return response()->json([
            "status" => true,
            "data" => $product
        ]);
    }

    public function getBySupplier(Request $request)
    {
        $supplier_id = intval($request->input('supplier_id') ?: $request->query('supplier_id', 0));

        if (!$supplier_id) {
            return response()->json([
                "status" => false,
                "message" => "supplier_id required"
            ]);
        }

        $products = DB::table('supplier_products as sp')
            ->leftJoin('categories as cat', 'sp.category_id', '=', 'cat.id')
            ->leftJoin('subcategories as sub', 'sp.subcategory_id', '=', 'sub.id')
            ->leftJoin('brands as b', 'sp.brand_id', '=', 'b.id')
            ->select('sp.*', 'cat.name as category_name', 'sub.name as subcategory_name', 'b.name as brand_name')
            ->where('sp.supplier_id', $supplier_id)
            ->orderBy('sp.id', 'desc')
            ->get();

        return response()->json([
            "status" => true,
            "data" => $products
        ]);
    }

    public function update(Request $request)
    {
        $id             = intval($request->input('id', 0));
        $product_name   = trim($request->input('product_name', ''));
        $product_code   = trim($request->input('product_code', ''));
        $category_id    = intval($request->input('category_id', 0));
        $subcategory_id = intval($request->input('subcategory_id', 0));
        $brand_id       = intval($request->input('brand_id', 0));
        $price          = floatval($request->input('price', 0));
        $stock          = intval($request->input('stock', 0));
        $gst_percentage = floatval($request->input('gst_percentage', 0));
        $barcode        = trim($request->input('barcode', ''));
        $unit           = trim($request->input('unit', ''));

        if (!$id || !$product_name) {
            return response()->json([
                "status" => false,
                "message" => "ID and Product Name are required"
            ]);
        }

        SupplierProduct::where('id', $id)->update([
            'product_name' => $product_name,
            'product_code' => $product_code ?: null,
            'category_id' => $category_id,
            'subcategory_id' => $subcategory_id,
            'brand_id' => $brand_id,
            'price' => $price,
            'stock' => $stock,
            'gst_percentage' => $gst_percentage,
            'barcode' => $barcode ?: null,
            'unit' => $unit
        ]);

        return response()->json([
            "status" => true,
            "message" => "Supplier product updated successfully"
        ]);
    }
}
