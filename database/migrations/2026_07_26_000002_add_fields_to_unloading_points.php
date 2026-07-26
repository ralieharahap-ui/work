<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unloading_points', function (Blueprint $table) {
            // Harga (yang dibayar customer) per satuan sebelum PPN. Include PPN = price * 1.11
            $table->decimal('price', 15, 2)->default(0)->after('capacity');
            $table->boolean('has_jetty')->default(false)->after('price');   // apakah ada dermaga
            $table->string('jetty_name')->nullable()->after('has_jetty');   // nama dermaga (jika ada)
        });
    }

    public function down(): void
    {
        Schema::table('unloading_points', function (Blueprint $table) {
            $table->dropColumn(['price', 'has_jetty', 'jetty_name']);
        });
    }
};
