<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function changePassword(Request $request)
    {
        $admin_id     = intval($request->input('admin_id', 0));
        $old_password = trim($request->input('old_password', ''));
        $new_password = trim($request->input('new_password', ''));

        if (!$admin_id || !$old_password || !$new_password) {
            return response()->json([
                "status" => false,
                "message" => "All fields are required"
            ]);
        }

        $user = User::where('id', $admin_id)->whereIn('role', ['admin', 'cashier'])->first();
        if (!$user) {
            return response()->json([
                "status" => false,
                "message" => "Admin not found"
            ]);
        }

        if (!Hash::check($old_password, $user->password)) {
            return response()->json([
                "status" => false,
                "message" => "Old password is incorrect"
            ]);
        }

        $user->update(['password' => Hash::make($new_password)]);

        return response()->json([
            "status" => true,
            "message" => "Password updated successfully"
        ]);
    }

    public function createAdmin(Request $request)
    {
        $name         = trim($request->input('name', ''));
        $email        = trim($request->input('email', ''));
        $password     = $request->input('password', '');
        $role         = $request->input('role', '');
        $company_id   = intval($request->input('company_id', 0));
        $requested_by = intval($request->input('requested_by', 0));

        if (!$name || !$email || !$password || !$role) {
            return response()->json([
                "status" => false,
                "message" => "All fields required"
            ]);
        }

        if (!in_array($role, ['superadmin', 'admin', 'cashier'])) {
            return response()->json([
                "status" => false,
                "message" => "Invalid role"
            ]);
        }

        if ($role === 'cashier' && !$company_id) {
            return response()->json([
                "status" => false,
                "message" => "Company ID required"
            ]);
        }

        $check = User::where('email', $email)->exists();
        if ($check) {
            return response()->json([
                "status" => false,
                "message" => "Email already exists"
            ]);
        }

        $hashed = Hash::make($password);

        if ($role === 'cashier') {
            $totalCashiers = User::where('company_id', $company_id)->where('role', 'cashier')->count();
            if ($totalCashiers >= 3) {
                DB::table('cashier_requests')->insert([
                    'company_id' => $company_id,
                    'requested_by' => $requested_by ?: null,
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashed
                ]);

                return response()->json([
                    "status" => false,
                    "request_sent" => true,
                    "message" => "Cashier limit reached. Request sent to Super Admin."
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $company_val = ($role === 'admin' || $role === 'superadmin') ? null : $company_id;

            User::create([
                'name' => $name,
                'email' => $email,
                'password' => $hashed,
                'role' => $role,
                'company_id' => $company_val ?: null
            ]);

            DB::commit();

            return response()->json([
                "status" => true,
                "message" => ucfirst($role) . " created successfully"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" => false,
                "message" => $e->getMessage()
            ]);
        }
    }

    public function getAdmins()
    {
        $admins = User::where('role', 'admin')->select('id', 'name', 'email', 'status')->orderBy('id', 'desc')->get();

        return response()->json([
            "status" => true,
            "data"   => $admins
        ]);
    }

    public function getAdminById(Request $request)
    {
        $id = intval($request->query('id', 0));
        $admin = User::where('id', $id)->where('role', 'admin')->select('id', 'name', 'email', 'status')->first();

        if (!$admin) {
            return response()->json([
                "status" => false,
                "message" => "Admin not found"
            ]);
        }

        return response()->json([
            "status" => true,
            "data"   => $admin
        ]);
    }

    public function toggleStatusAdmin(Request $request)
    {
        $id     = intval($request->input('id', 0));
        $status = $request->input('status', '');

        if (!$id || !$status) {
            return response()->json([
                "status" => false,
                "success" => false,
                "message" => "Invalid data"
            ]);
        }

        $user = User::where('id', $id)->where('role', 'admin')->first();
        if ($user) {
            $user->update(['status' => $status]);
            return response()->json([
                "status" => true,
                "success" => true,
                "message" => "Status updated"
            ]);
        }

        return response()->json([
            "status" => false,
            "success" => false,
            "message" => "Admin not found"
        ]);
    }

    public function updateAdmin(Request $request)
    {
        $id       = intval($request->input('id', 0));
        $name     = trim($request->input('name', ''));
        $email    = trim($request->input('email', ''));
        $password = trim($request->input('password', ''));

        if (!$id || !$name || !$email) {
            return response()->json([
                "status" => false,
                "message" => "Required fields missing"
            ]);
        }

        $check = User::where('email', $email)->where('id', '!=', $id)->exists();
        if ($check) {
            return response()->json([
                "status" => false,
                "message" => "Email already exists"
            ]);
        }

        $user = User::where('id', $id)->where('role', 'admin')->first();
        if (!$user) {
            return response()->json([
                "status" => false,
                "message" => "Admin not found"
            ]);
        }

        $updateData = [
            'name' => $name,
            'email' => $email
        ];

        if (!empty($password)) {
            $updateData['password'] = Hash::make($password);
        }

        $user->update($updateData);

        return response()->json([
            "status" => true,
            "message" => "Admin updated successfully"
        ]);
    }
}
