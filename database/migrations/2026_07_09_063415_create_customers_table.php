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
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name', 100)->nullable();
                $table->string('phone', 20)->nullable();
                $table->text('address')->nullable();
                $table->string('type', 50)->nullable();
                $table->tinyInteger('credit_enabled')->default(0);
                $table->tinyInteger('is_deleted')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->integer('admin_id')->nullable();
                $table->decimal('credit_limit', 10, 2)->default(0.00);
                $table->integer('credit_days')->default(0);
                $table->string('gst_no', 20)->nullable();
                $table->decimal('pending_amount', 10, 2)->default(0.00);
                $table->decimal('advance_balance', 10, 2)->default(0.00);
                $table->integer('loyalty_points')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
