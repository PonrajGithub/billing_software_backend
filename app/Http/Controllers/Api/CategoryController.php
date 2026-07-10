<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function create(Request $request)
    {
        $name = trim($request->input('name', ''));
        $company_id = intval($request->input('company_id', 0));

        if (!$name || !$company_id) {
            return response()->json([
                "status" => false,
                "message" => "Required fields missing"
            ]);
        }

        Category::create([
            'name' => $name,
            'company_id' => $company_id,
            'status' => 'active',
            'is_deleted' => 0
        ]);

        return response()->json([
            "status" => true,
            "message" => "Category created successfully"
        ]);
    }

    public function delete(Request $request)
    {
        $id = intval($request->input('id', 0));
        if (!$id) {
            return response()->json([
                "status" => false,
                "message" => "ID required"
            ]);
        }

        DB::beginTransaction();
        try {
            Product::where('category_id', $id)->delete();
            Category::where('id', $id)->delete();

            DB::commit();
            return response()->json([
                "status" => true,
                "message" => "Category + related products deleted"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" => false,
                "message" => $e->getMessage()
            ]);
        }
    }

    public function getActiveCategory(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));
        $categories = Category::where('company_id', $company_id)
            ->where('status', 'active')
            ->where('is_deleted', 0)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            "status" => true,
            "data" => $categories
        ]);
    }

    public function getAll(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));
        $categories = Category::where('company_id', $company_id)
            ->where('is_deleted', 0)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            "status" => true,
            "data" => $categories
        ]);
    }

    public function getById(Request $request)
    {
        $id = intval($request->input('id') ?: $request->query('id', 0));
        $category = Category::where('id', $id)->first();

        if (!$category) {
            return response()->json([
                "status" => false,
                "message" => "Category not found"
            ]);
        }

        return response()->json([
            "status" => true,
            "data" => $category
        ]);
    }

    public function toggleCategoryStatus(Request $request)
    {
        $id = intval($request->input('id', 0));
        $status = $request->input('status', '');

        if (!$id || !$status) {
            return response()->json([
                "status" => false,
                "message" => "Invalid data"
            ]);
        }

        Category::where('id', $id)->update(['status' => $status]);

        return response()->json([
            "status" => true,
            "message" => "Status updated successfully"
        ]);
    }

    public function update(Request $request)
    {
        $id = intval($request->input('id', 0));
        $name = trim($request->input('name', ''));

        if (!$id || !$name) {
            return response()->json([
                "status" => false,
                "message" => "Required fields missing"
            ]);
        }

        Category::where('id', $id)->update(['name' => $name]);

        return response()->json([
            "status" => true,
            "message" => "Category updated successfully"
        ]);
    }
}
