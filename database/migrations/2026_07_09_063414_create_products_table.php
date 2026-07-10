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
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('product_name', 150)->nullable();
                $table->string('product_code', 100)->nullable();
                $table->integer('category_id')->nullable();
                $table->integer('subcategory_id')->nullable();
                $table->integer('brand_id')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->integer('stock')->nullable();
                $table->string('barcode', 100)->nullable();
                $table->string('unit', 20)->nullable();
                $table->decimal('gst_percentage', 5, 2)->nullable();
                $table->integer('company_id')->nullable();
                $table->tinyInteger('is_deleted')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->integer('supplier_id')->nullable();

                // Foreign Key and Indexes
                $table->index('supplier_id', 'fk_products_supplier');
                $table->foreign('supplier_id', 'fk_products_supplier')->references('id')->on('suppliers')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
