<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds missing company_code and gst_type columns to company_requests table.
     */
    public function up(): void
    {
        Schema::table('company_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('company_requests', 'company_code')) {
                $table->string('company_code', 50)->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('company_requests', 'gst_type')) {
                $table->string('gst_type', 20)->nullable()->default('with_gst')->after('gstin');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_requests', function (Blueprint $table) {
            $table->dropColumn(['company_code', 'gst_type']);
        });
    }
};
