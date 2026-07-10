<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CreditSetting;

class CreditController extends Controller
{
    public function get(Request $request)
    {
        $company_id = intval($request->input('company_id') ?: $request->query('company_id', 0));
        if (!$company_id) {
            return response()->json(["status" => false, "message" => "Company ID required"]);
        }

        $setting = CreditSetting::where('company_id', $company_id)->first();
        if ($setting) {
            return response()->json([
                "status" => true,
                "data" => [
                    "default_credit_days" => intval($setting->default_credit_days)
                ]
            ]);
        } else {
            return response()->json([
                "status" => true,
                "data" => [
                    "default_credit_days" => 30
                ]
            ]);
        }
    }

    public function save(Request $request)
    {
        $company_id = intval($request->input('company_id', 0));
        $days = intval($request->input('default_credit_days', 30));

        if (!$company_id || $days <= 0) {
            return response()->json(["status" => false, "message" => "Invalid data"]);
        }

        CreditSetting::updateOrCreate(
            ['company_id' => $company_id],
            ['default_credit_days' => $days]
        );

        return response()->json(["status" => true]);
    }
}
