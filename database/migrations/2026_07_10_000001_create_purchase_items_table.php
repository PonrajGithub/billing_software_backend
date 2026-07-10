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
        if (!Schema::hasTable('purchase_items')) {
            Schema::create('purchase_items', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('purchase_id');
                $table->integer('product_id')->nullable();
                $table->string('product_name', 150);
                $table->string('product_code', 100)->nullable();
                $table->string('barcode', 100)->nullable();
                $table->string('category_name', 100)->nullable();
                $table->string('subcategory_name', 100)->nullable();
                $table->string('brand_name', 100)->nullable();
                $table->integer('category_id')->nullable();
                $table->integer('subcategory_id')->nullable();
                $table->integer('brand_id')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->integer('quantity')->default(0);
                $table->string('unit', 20)->nullable();
                $table->decimal('gst_percentage', 5, 2)->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                // Foreign Keys
                $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
                $table->foreign('subcategory_id')->references('id')->on('subcategories')->onDelete('set null');
                $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
