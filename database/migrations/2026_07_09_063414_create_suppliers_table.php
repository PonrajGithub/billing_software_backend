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
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('company_id');
                $table->string('supplier_name', 150);
                $table->string('mobile_number', 10);
                $table->string('alt_mobile', 10)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('gst_number', 20)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('district', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('pincode', 10)->nullable();
                $table->string('country', 100)->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->tinyInteger('is_deleted')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                // Indexes
                $table->index('company_id', 'idx_company_id');
                $table->index('mobile_number', 'idx_mobile_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
