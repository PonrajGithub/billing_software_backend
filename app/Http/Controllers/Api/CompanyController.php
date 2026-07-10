<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\CompanyRequest;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function addCompany(Request $request)
    {
        $admin_id = intval($request->input('admin_id', 0));
        $company_name = trim($request->input('company_name', ''));
        $company_address = trim($request->input('company_address', ''));
        $company_code = trim($request->input('company_code', ''));
        $gstin = strtoupper(trim($request->input('gstin', '')));
        $gst_type = $request->input('gst_type', 'with_gst');
        $phone = trim($request->input('phone', ''));
        $logo = $request->input('logo', '');

        $db_path = '';

        if (!empty($logo)) {
            $logo_clean = preg_replace('#^data:image/\w+;base64,#i', '', $logo);
            $image = base64_decode($logo_clean);
            if ($image !== false) {
                $upload_dir = public_path('uploads/');
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_name = time() . ".png";
                if (file_put_contents($upload_dir . $file_name, $image)) {
                    $db_path = "uploads/" . $file_name;
                }
            }
        }

        $gstRegex   = "/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/";
        $phoneRegex = "/^[0-9]{10}$/";

        if (!$company_name || !$company_code || !$company_address) {
            return response()->json([
                "status" => false,
                "message" => "All company fields required"
            ]);
        }

        if (!empty($gstin) && !preg_match($gstRegex, $gstin)) {
            return response()->json([
                "status" => false,
                "message" => "Invalid GSTIN"
            ]);
        }

        if (!preg_match($phoneRegex, $phone)) {
            return response()->json([
                "status" => false,
                "message" => "Phone must be 10 digits"
            ]);
        }

        if ($admin_id > 0) {
            $count = Company::where('admin_id', $admin_id)->where('is_deleted', 0)->count();
            if ($count >= 3) {
                $checkReq = CompanyRequest::where('admin_id', $admin_id)
                    ->where('company_name', $company_name)
                    ->where('status', 'pending')
                    ->exists();

                if ($checkReq) {
                    return response()->json([
                        "status" => false,
                        "message" => "Request already pending"
                    ]);
                }

                CompanyRequest::create([
                    'admin_id' => $admin_id,
                    'company_name' => $company_name,
                    'company_code' => $company_code,
                    'company_address' => $company_address,
                    'gstin' => $gstin,
                    'gst_type' => $gst_type,
                    'phone' => $phone,
                    'logo' => $logo,
                    'status' => 'pending'
                ]);

                return response()->json([
                    "status" => false,
                    "request_sent" => true,
                    "message" => "Maximum 3 companies reached. Request sent to Super Admin."
                ]);
            }
        }

        Company::create([
            'admin_id' => $admin_id ?: null,
            'company_name' => $company_name,
            'company_code' => $company_code,
            'company_address' => $company_address,
            'gstin' => $gstin,
            'gst_type' => $gst_type,
            'phone' => $phone,
            'logo' => $db_path,
            'status' => 'active',
            'is_deleted' => 0
        ]);

        return response()->json([
            "status" => true,
            "message" => "Company created successfully"
        ]);
    }

    public function deleteCompany(Request $request)
    {
        $id = intval($request->input('id', 0));
        Company::where('id', $id)->update(['is_deleted' => 1]);

        return response()->json([
            "status" => true,
            "message" => "Company deleted successfully"
        ]);
    }

    public function getCompanies()
    {
        $companies = Company::where('is_deleted', 0)->orderBy('id', 'desc')->get();
        return response()->json([
            "status" => true,
            "data" => $companies
        ]);
    }

    public function getCompaniesByAdmin(Request $request)
    {
        $admin_id = intval($request->input('admin_id') ?: $request->query('admin_id', 0));
        $companies = Company::where('admin_id', $admin_id)->where('is_deleted', 0)->orderBy('id', 'desc')->get();
        return response()->json([
            "status" => true,
            "data" => $companies
        ]);
    }

    public function getCompanyById(Request $request)
    {
        $id = intval($request->input('id') ?: $request->query('id', 0));
        $company = Company::where('id', $id)->first();

        if (!$company) {
            return response()->json([
                "status" => false,
                "message" => "Company not found"
            ]);
        }

        return response()->json([
            "status" => true,
            "data" => $company
        ]);
    }

    public function toggleCompanyStatus(Request $request)
    {
        $id = intval($request->input('id', 0));
        $status = $request->input('status', '');

        if (!$id || !$status) {
            return response()->json([
                "status" => false,
                "message" => "Invalid data"
            ]);
        }

        Company::where('id', $id)->update(['status' => $status]);

        return response()->json([
            "status" => true,
            "message" => "Company status updated"
        ]);
    }

    public function updateCompany(Request $request)
    {
        $id = intval($request->input('id', 0));
        $company_name = trim($request->input('company_name', ''));
        $company_address = trim($request->input('company_address', ''));
        $company_code = trim($request->input('company_code', ''));
        $gstin = strtoupper(trim($request->input('gstin', '')));
        $gst_type = $request->input('gst_type', 'with_gst');
        $phone = trim($request->input('phone', ''));
        $logo = $request->input('logo', '');

        $company = Company::find($id);
        if (!$company) {
            return response()->json([
                "status" => false,
                "message" => "Company not found"
            ]);
        }

        $db_path = $company->logo;

        if (!empty($logo) && (strpos($logo, 'data:image') === 0 || strlen($logo) > 100)) {
            $logo_clean = preg_replace('#^data:image/\w+;base64,#i', '', $logo);
            $image = base64_decode($logo_clean);
            if ($image !== false) {
                $upload_dir = public_path('uploads/');
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_name = time() . ".png";
                if (file_put_contents($upload_dir . $file_name, $image)) {
                    $db_path = "uploads/" . $file_name;
                }
            }
        }

        $company->update([
            'company_name' => $company_name ?: $company->company_name,
            'company_code' => $company_code ?: $company->company_code,
            'company_address' => $company_address ?: $company->company_address,
            'gstin' => $gstin,
            'gst_type' => $gst_type ?: $company->gst_type,
            'phone' => $phone ?: $company->phone,
            'logo' => $db_path
        ]);

        return response()->json([
            "status" => true,
            "message" => "Company updated successfully"
        ]);
    }

    // ── COMPANY REQUESTS ──

    public function approveCompanyRequest(Request $request)
    {
        $id = intval($request->input('request_id') ?: $request->input('id', 0));
        $companyReq = CompanyRequest::find($id);

        if (!$companyReq) {
            return response()->json([
                "status" => false,
                "message" => "Request not found"
            ]);
        }

        $logoPath = '';
        if (!empty($companyReq->logo)) {
            $logo_clean = preg_replace('#^data:image/\w+;base64,#i', '', $companyReq->logo);
            $image = base64_decode($logo_clean);
            $upload_dir = public_path('uploads/');
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . ".png";
            file_put_contents($upload_dir . $file_name, $image);
            $logoPath = "uploads/" . $file_name;
        }

        DB::beginTransaction();
        try {
            Company::create([
                'admin_id' => $companyReq->admin_id,
                'company_name' => $companyReq->company_name,
                'company_code' => $companyReq->company_code,
                'company_address' => $companyReq->company_address,
                'gstin' => $companyReq->gstin,
                'gst_type' => $companyReq->gst_type,
                'phone' => $companyReq->phone,
                'logo' => $logoPath,
                'status' => 'active',
                'is_deleted' => 0
            ]);

            $companyReq->update(['status' => 'approved']);

            DB::commit();
            return response()->json([
                "status" => true,
                "message" => "Company approved successfully"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" => false,
                "message" => $e->getMessage()
            ]);
        }
    }

    public function getCompanyRequests()
    {
        $requests = DB::table('company_requests as cr')
            ->leftJoin('users as u', 'u.id', '=', 'cr.admin_id')
            ->select('cr.*', 'u.name as requested_user')
            ->where('cr.status', 'pending')
            ->orderBy('cr.id', 'desc')
            ->get();

        return response()->json([
            "status" => true,
            "data" => $requests
        ]);
    }

    public function rejectCompanyRequest(Request $request)
    {
        $id = intval($request->input('request_id') ?: $request->input('id', 0));
        CompanyRequest::where('id', $id)->update(['status' => 'rejected']);

        return response()->json([
            "status" => true,
            "message" => "Request rejected successfully"
        ]);
    }
}
