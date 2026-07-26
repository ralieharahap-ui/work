<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('palm_oil_sources', function (Blueprint $table) {
            // Harga dasar cangkang per satuan (Rp), sebelum PPN. Harga include PPN = price * 1.11
            $table->decimal('price', 15, 2)->default(0)->after('stock_min');
        });
    }

    public function down(): void
    {
        Schema::table('palm_oil_sources', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
