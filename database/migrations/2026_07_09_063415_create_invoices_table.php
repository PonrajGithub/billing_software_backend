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
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('invoice_no', 100)->nullable();
                $table->integer('customer_id')->nullable();
                $table->string('customer_name', 100)->nullable();
                $table->string('customer_phone', 20)->nullable();
                $table->integer('cashier_id')->nullable();
                $table->text('products')->nullable();
                $table->decimal('sub_total', 10, 2)->nullable();
                $table->decimal('gst_total', 10, 2)->nullable();
                $table->decimal('total_amount', 10, 2)->nullable();
                $table->decimal('paid_amount', 10, 2)->nullable();
                $table->decimal('balance_amount', 10, 2)->nullable();
                $table->decimal('previous_balance', 10, 2)->nullable();
                $table->decimal('current_balance', 10, 2)->nullable();
                $table->enum('payment_method', ['cash', 'online', 'upi', 'credit', 'loyalty'])->nullable();
                $table->enum('payment_type', ['cash', 'credit'])->nullable();
                $table->enum('gst_type', ['with_gst', 'without_gst'])->nullable();
                $table->string('gst_no', 100)->nullable();
                $table->enum('payment_status', ['paid', 'partial', 'not_paid'])->nullable();
                $table->integer('company_id')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
