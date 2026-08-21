<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom fs_group (Kelompok Financial Statement) ke tabel accounts,
 * mengikuti restatement COA PT GEP: mis. Aset Lancar, Aset Kontra, Persediaan,
 * Pajak, Aset Tetap, Aset Takberwujud, Liabilitas Lancar, Liabilitas Pajak,
 * Ekuitas, Pendapatan, Pendapatan Lain, COGS, OPEX, Beban Lain.
 *
 * Bersifat menambah kolom (guard Schema::hasColumn) — aman dijalankan berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'fs_group')) {
                $table->string('fs_group')->nullable()->after('account_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'fs_group')) {
                $table->dropColumn('fs_group');
            }
        });
    }
};
