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
        if (!Schema::hasTable('supplier_products')) {
            Schema::create('supplier_products', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('supplier_id');
                $table->string('product_name', 150);
                $table->string('product_code', 100)->nullable();
                $table->integer('category_id')->nullable();
                $table->integer('subcategory_id')->nullable();
                $table->integer('brand_id')->nullable();
                $table->integer('company_id');
                $table->decimal('price', 10, 2)->nullable();
                $table->integer('stock')->nullable();
                $table->decimal('gst_percentage', 5, 2)->nullable();
                $table->string('barcode', 100)->nullable();
                $table->string('unit', 20)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                // Foreign Key and Indexes
                $table->index('supplier_id', 'fk_supplier_products_supplier');
                $table->foreign('supplier_id', 'fk_supplier_products_supplier')->references('id')->on('suppliers')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
