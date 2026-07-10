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
        if (!Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name', 100);
                $table->integer('category_id');
                $table->integer('subcategory_id');
                $table->integer('company_id');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->tinyInteger('is_deleted')->default(0);
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
