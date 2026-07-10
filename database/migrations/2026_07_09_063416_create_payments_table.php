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
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('company_id')->nullable();
                $table->integer('invoice_id')->nullable();
                $table->string('invoice_no', 100)->nullable();
                $table->integer('customer_id')->nullable();
                $table->decimal('total_amount', 10, 2)->nullable();
                $table->decimal('paid_amount', 10, 2)->nullable();
                $table->decimal('balance_amount', 10, 2)->nullable();
                $table->enum('payment_method', ['cash', 'online', 'upi', 'credit', 'loyalty'])->nullable();
                $table->enum('payment_status', ['paid', 'partial', 'not_paid'])->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
