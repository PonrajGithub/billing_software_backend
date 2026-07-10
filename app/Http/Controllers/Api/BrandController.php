<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Brand;

class BrandController extends Controller
{
    public function create(Request $request)
    {
        $name = trim($request->input('name', ''));
        $category_id = intval($request->input('category_id', 0));
        $subcategory_id = intval($request->input('subcategory_id', 0));
        $company_id = intval($request->input('company_id', 0));

        if (!$name || !$category_id || !$subcategory_id || !$company_id) {
            return response()->json([
                "status" => false,
                "message" => "All fields are required"
            ]);
        }

        Brand::create([
            'name' => $name,
            'category_id' => $category_id,
            'subcategory_id' => $subcategory_id,
            'company_id' => $company_id,
            'status' => 'active',
            'is_deleted' => 0
        ]);

        return response()->json([
            "status" => true,
            "message" => "Brand created successfully"
        ]);
    }

    public function getActiveBrand(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id'));
        $category_id = intval($request->input('category_id') ?: $request->query('category_id'));
        $subcategory_id = intval($request->input('subcategory_id') ?: $request->query('subcategory_id'));

        $query = Brand::where('company_id', $company_id)->where('status', 'active')->where('is_deleted', 0);

        if ($category_id > 0) {
            $query->where('category_id', $category_id);
        }
        if ($subcategory_id > 0) {
            $query->where('subcategory_id', $subcategory_id);
        }

        $brands = $query->orderBy('id', 'desc')->get();

        return response()->json([
            "status" => true,
            "data" => $brands
        ]);
    }

    public function getAll(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id'));
        $brands = \Illuminate\Support\Facades\DB::table('brands as b')
            ->leftJoin('categories as c', 'b.category_id', '=', 'c.id')
            ->leftJoin('subcategories as sc', 'b.subcategory_id', '=', 'sc.id')
            ->select('b.*', 'c.name as category_name', 'sc.name as subcategory_name')
            ->where('b.company_id', $company_id)
            ->where('b.is_deleted', 0)
            ->orderBy('b.id', 'desc')
            ->get();

        return response()->json([
            "status" => true,
            "data" => $brands
        ]);
    }

    public function getById(Request $request)
    {
        $id = intval($request->input('id') ?: $request->query('id'));
        $brand = Brand::where('id', $id)->first();

        if (!$brand) {
            return response()->json([
                "status" => false,
                "message" => "Brand not found"
            ]);
        }

        return response()->json([
            "status" => true,
            "data" => $brand
        ]);
    }

    public function statusToggle(Request $request)
    {
        $id = intval($request->input('id'));
        $status = $request->input('status');

        if (!$id || !$status) {
            return response()->json([
                "status" => false,
                "message" => "Invalid data"
            ]);
        }

        Brand::where('id', $id)->update(['status' => $status]);

        return response()->json([
            "status" => true,
            "message" => "Status updated successfully"
        ]);
    }

    public function update(Request $request)
    {
        $id = intval($request->input('id'));
        $name = trim($request->input('name', ''));
        $category_id = intval($request->input('category_id', 0));
        $subcategory_id = intval($request->input('subcategory_id', 0));

        if (!$id || !$name || !$category_id || !$subcategory_id) {
            return response()->json([
                "status" => false,
                "message" => "All fields are required"
            ]);
        }

        Brand::where('id', $id)->update([
            'name' => $name,
            'category_id' => $category_id,
            'subcategory_id' => $subcategory_id
        ]);

        return response()->json([
            "status" => true,
            "message" => "Brand updated successfully"
        ]);
    }
}
