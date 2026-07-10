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
        if (!Schema::hasTable('cashier_requests')) {
            Schema::create('cashier_requests', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('admin_id');
                $table->integer('company_id');
                $table->string('cashier_name', 100);
                $table->string('cashier_email', 100);
                $table->string('cashier_password', 255);
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_requests');
    }
};
