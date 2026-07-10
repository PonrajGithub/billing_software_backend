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
        if (!Schema::hasTable('purchases')) {
            Schema::create('purchases', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('purchase_no', 100)->nullable();
                $table->integer('supplier_id')->nullable();
                $table->integer('company_id')->nullable();
                $table->date('purchase_date')->nullable();
                $table->decimal('sub_total', 10, 2)->default(0);
                $table->decimal('gst_total', 10, 2)->default(0);
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->decimal('paid_amount', 10, 2)->default(0);
                $table->decimal('balance_amount', 10, 2)->default(0);
                $table->enum('status', ['draft', 'submitted'])->default('draft');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                // Foreign Keys
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
