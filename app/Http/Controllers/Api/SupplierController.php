<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function create(Request $request)
    {
        $company_id     = intval($request->input('company_id', 0));
        $supplier_name  = trim($request->input('supplier_name', ''));
        $mobile_number  = trim($request->input('mobile_number', ''));
        $alt_mobile     = trim($request->input('alt_mobile', ''));
        $email          = trim($request->input('email', ''));
        $gst_number     = trim($request->input('gst_number', ''));
        $address        = trim($request->input('address', ''));
        $city           = trim($request->input('city', ''));
        $district       = trim($request->input('district', ''));
        $state          = trim($request->input('state', ''));
        $pincode        = trim($request->input('pincode', ''));
        $country        = trim($request->input('country', ''));

        if (!$company_id || !$supplier_name || !$mobile_number) {
            return response()->json([
                "status" => false,
                "message" => "Company, Supplier Name, and Mobile Number are required"
            ]);
        }

        Supplier::create([
            'company_id' => $company_id,
            'supplier_name' => $supplier_name,
            'mobile_number' => $mobile_number,
            'alt_mobile' => $alt_mobile ?: null,
            'email' => $email ?: null,
            'gst_number' => $gst_number ?: null,
            'address' => $address ?: null,
            'city' => $city ?: null,
            'district' => $district ?: null,
            'state' => $state ?: null,
            'pincode' => $pincode ?: null,
            'country' => $country ?: null,
            'status' => 'active',
            'is_deleted' => 0
        ]);

        return response()->json([
            "status" => true,
            "message" => "Supplier created successfully"
        ]);
    }

    public function getAll(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));
        $suppliers = Supplier::where('company_id', $company_id)->where('is_deleted', 0)->orderBy('id', 'desc')->get();

        return response()->json([
            "status" => true,
            "data" => $suppliers
        ]);
    }

    public function getById(Request $request)
    {
        $id = intval($request->input('id') ?: $request->query('id', 0));
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                "status" => false,
                "message" => "Supplier not found"
            ]);
        }

        return response()->json([
            "status" => true,
            "data" => $supplier
        ]);
    }

    public function toggleSupplierStatus(Request $request)
    {
        $id = intval($request->input('id', 0));
        $status = $request->input('status', '');

        if (!$id || !$status) {
            return response()->json([
                "status" => false,
                "message" => "Invalid data"
            ]);
        }

        Supplier::where('id', $id)->update(['status' => $status]);

        return response()->json([
            "status" => true,
            "message" => "Supplier status updated successfully"
        ]);
    }

    public function update(Request $request)
    {
        $id             = intval($request->input('id', 0));
        $supplier_name  = trim($request->input('supplier_name', ''));
        $mobile_number  = trim($request->input('mobile_number', ''));
        $alt_mobile     = trim($request->input('alt_mobile', ''));
        $email          = trim($request->input('email', ''));
        $gst_number     = trim($request->input('gst_number', ''));
        $address        = trim($request->input('address', ''));
        $city           = trim($request->input('city', ''));
        $district       = trim($request->input('district', ''));
        $state          = trim($request->input('state', ''));
        $pincode        = trim($request->input('pincode', ''));
        $country        = trim($request->input('country', ''));

        if (!$id || !$supplier_name || !$mobile_number) {
            return response()->json([
                "status" => false,
                "message" => "Supplier Name and Mobile Number are required"
            ]);
        }

        Supplier::where('id', $id)->update([
            'supplier_name' => $supplier_name,
            'mobile_number' => $mobile_number,
            'alt_mobile' => $alt_mobile ?: null,
            'email' => $email ?: null,
            'gst_number' => $gst_number ?: null,
            'address' => $address ?: null,
            'city' => $city ?: null,
            'district' => $district ?: null,
            'state' => $state ?: null,
            'pincode' => $pincode ?: null,
            'country' => $country ?: null
        ]);

        return response()->json([
            "status" => true,
            "message" => "Supplier updated successfully"
        ]);
    }
}
