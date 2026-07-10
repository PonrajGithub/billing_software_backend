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
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'company_id')) {
                $table->dropColumn('company_id');
            }
            if (!Schema::hasColumn('customers', 'type')) {
                $table->string('type', 50)->nullable()->after('address');
            }
            if (!Schema::hasColumn('customers', 'credit_enabled')) {
                $table->tinyInteger('credit_enabled')->default(0)->after('type');
            }
            if (!Schema::hasColumn('customers', 'gst_no')) {
                $table->string('gst_no', 20)->nullable()->after('credit_days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('company_id')->nullable()->after('address');
            if (Schema::hasColumn('customers', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('customers', 'credit_enabled')) {
                $table->dropColumn('credit_enabled');
            }
            if (Schema::hasColumn('customers', 'gst_no')) {
                $table->dropColumn('gst_no');
            }
        });
    }
};
