<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['superadmin', 'admin', 'cashier'])->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'company_id')) {
                $table->integer('company_id')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('created_at');
            }
            if (!Schema::hasColumn('users', 'admin_id')) {
                $table->integer('admin_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'otp')) {
                $table->string('otp', 10)->nullable()->after('admin_id');
            }
            if (!Schema::hasColumn('users', 'otp_expiry')) {
                $table->timestamp('otp_expiry')->nullable()->after('otp');
            }
        });

        // Insert Superadmin user if it doesn't exist
        $exists = DB::table('users')->where('email', 'superadmin@gmail.com')->exists();
        if (!$exists) {
            DB::table('users')->insert([
                'name' => 'Super Admin',
                'email' => 'superadmin@gmail.com',
                'password' => Hash::make('superadmin@123'),
                'role' => 'superadmin',
                'status' => 'active',
                'created_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'company_id', 'status', 'admin_id', 'otp', 'otp_expiry']);
        });
    }
};
