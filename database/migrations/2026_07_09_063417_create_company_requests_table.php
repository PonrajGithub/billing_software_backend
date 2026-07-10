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
        if (!Schema::hasTable('company_requests')) {
            Schema::create('company_requests', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('admin_id')->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('owner_name', 100)->nullable();
                $table->string('owner_email', 100)->nullable();
                $table->string('logo', 255)->nullable();
                $table->text('company_address')->nullable();
                $table->string('gstin', 100)->nullable();
                $table->string('phone', 20)->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
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
        Schema::dropIfExists('company_requests');
    }
};
