<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subcategory;
use Illuminate\Support\Facades\DB;

class SubcategoryController extends Controller
{
    public function create(Request $request)
    {
        $name = trim($request->input('name', ''));
        $category_id = intval($request->input('category_id', 0));
        $company_id = intval($request->input('company_id', 0));

        if (!$name || !$category_id || !$company_id) {
            return response()->json([
                "status" => false,
                "message" => "All fields are required"
            ]);
        }

        Subcategory::create([
            'name' => $name,
            'category_id' => $category_id,
            'company_id' => $company_id,
            'status' => 'active',
            'is_deleted' => 0
        ]);

        return response()->json([
            "status" => true,
            "message" => "Subcategory created successfully"
        ]);
    }

    public function getActiveSubcategory(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));
        $category_id = intval($request->input('category_id') ?: $request->query('category_id', 0));

        $query = Subcategory::where('company_id', $company_id)->where('status', 'active')->where('is_deleted', 0);

        if ($category_id > 0) {
            $query->where('category_id', $category_id);
        }

        $subcategories = $query->orderBy('id', 'desc')->get();

        return response()->json([
            "status" => true,
            "data" => $subcategories
        ]);
    }

    public function getAll(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));
        $category_id = intval($request->input('category_id') ?: $request->query('category_id', 0));

        $query = DB::table('subcategories as sc')
            ->join('categories as c', 'sc.category_id', '=', 'c.id')
            ->select('sc.*', 'c.name as category_name')
            ->where('sc.company_id', $company_id)
            ->where('sc.is_deleted', 0);

        if ($category_id > 0) {
            $query->where('sc.category_id', $category_id);
        }

        $subcategories = $query->orderBy('sc.id', 'desc')->get();

        return response()->json([
            "status" => true,
            "data" => $subcategories
        ]);
    }

    public function getById(Request $request)
    {
        $id = intval($request->input('id') ?: $request->query('id', 0));
        $subcategory = Subcategory::find($id);

        if (!$subcategory) {
            return response()->json([
                "status" => false,
                "message" => "Subcategory not found"
            ]);
        }

        return response()->json([
            "status" => true,
            "data" => $subcategory
        ]);
    }

    public function statusToggle(Request $request)
    {
        $id = intval($request->input('id', 0));
        $status = $request->input('status', '');

        if (!$id || !$status) {
            return response()->json([
                "status" => false,
                "message" => "Invalid data"
            ]);
        }

        Subcategory::where('id', $id)->update(['status' => $status]);

        return response()->json([
            "status" => true,
            "message" => "Status updated successfully"
        ]);
    }

    public function update(Request $request)
    {
        $id = intval($request->input('id', 0));
        $name = trim($request->input('name', ''));
        $category_id = intval($request->input('category_id', 0));

        if (!$id || !$name || !$category_id) {
            return response()->json([
                "status" => false,
                "message" => "All fields are required"
            ]);
        }

        Subcategory::where('id', $id)->update([
            'name' => $name,
            'category_id' => $category_id
        ]);

        return response()->json([
            "status" => true,
            "message" => "Subcategory updated successfully"
        ]);
    }
}
