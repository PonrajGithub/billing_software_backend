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
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('admin_id')->nullable();
                $table->string('company_name', 255);
                $table->string('owner_name', 100)->nullable();
                $table->string('owner_email', 100)->nullable();
                $table->string('owner_password', 255)->nullable();
                $table->string('logo', 255)->nullable();
                $table->text('company_address')->nullable();
                $table->string('company_code', 100)->nullable();
                $table->string('gstin', 100)->nullable();
                $table->string('phone', 20)->nullable();
                $table->tinyInteger('is_deleted')->default(0);
                $table->timestamp('created_at')->useCurrent()->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->nullable();
                $table->enum('gst_type', ['with_gst', 'without_gst'])->default('with_gst');
                $table->integer('cashier_limit')->default(3);
                $table->enum('status', ['active', 'inactive'])->default('active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
