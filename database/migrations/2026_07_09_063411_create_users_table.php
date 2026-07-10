<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name', 100)->nullable();
                $table->string('email', 100)->unique()->nullable();
                $table->string('password', 255)->nullable();
                $table->enum('role', ['superadmin', 'admin', 'cashier'])->nullable();
                $table->integer('company_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->integer('admin_id')->nullable();
                $table->string('otp', 10)->nullable();
                $table->timestamp('otp_expiry')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
