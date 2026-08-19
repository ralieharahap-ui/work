<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan metadata "Control Account" ke tabel accounts (poin 1.7 spec):
 * - account_type   : sub-tipe halus (mis. Kas, Piutang Usaha, HPP, Beban Usaha, dst)
 * - normal_balance : posisi normal saldo — 'Db' (debet) atau 'Kr' (kredit)
 * - report         : peta laporan — 'NRC' (Neraca) atau 'LR' (Laba/Rugi)
 *
 * Bersifat menambah kolom (tidak mengubah/menghapus kolom lama). Guard
 * Schema::hasColumn agar aman dijalankan berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'account_type')) {
                $table->string('account_type')->nullable()->after('type');
            }
            if (! Schema::hasColumn('accounts', 'normal_balance')) {
                $table->string('normal_balance', 2)->nullable()->after('account_type'); // Db / Kr
            }
            if (! Schema::hasColumn('accounts', 'report')) {
                $table->string('report', 3)->nullable()->after('normal_balance');       // NRC / LR
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            foreach (['report', 'normal_balance', 'account_type'] as $col) {
                if (Schema::hasColumn('accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
