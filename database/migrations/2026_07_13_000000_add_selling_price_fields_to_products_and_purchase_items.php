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
        if (Schema::hasTable('purchase_items')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_items', 'selling_price')) {
                    $table->decimal('selling_price', 10, 2)->default(0)->nullable();
                }
                if (!Schema::hasColumn('purchase_items', 'selling_price_per_unit')) {
                    $table->string('selling_price_per_unit', 50)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_items')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropColumn(['selling_price', 'selling_price_per_unit']);
            });
        }
    }
};
