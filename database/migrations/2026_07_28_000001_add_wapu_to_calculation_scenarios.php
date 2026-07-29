<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculation_scenarios', function (Blueprint $table) {
            // Customer berstatus Wajib Pungut (mis. PLN): PPN tidak diterima perusahaan,
            // melainkan disetor customer langsung ke negara. Memengaruhi arus kas & margin.
            $table->boolean('is_wapu')->default(false)->after('price_customer');
        });
    }

    public function down(): void
    {
        Schema::table('calculation_scenarios', function (Blueprint $table) {
            $table->dropColumn('is_wapu');
        });
    }
};
