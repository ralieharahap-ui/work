<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Revisi COA berbasis PSAK 2026 (PT_GEP_COA_REVISI_PSAK_2026.md + hasil audit).
 *
 * - Akun lama dipetakan ke kode baru DI TEMPAT (id tetap) → jurnal, aset tetap & dokumen
 *   tetap menunjuk akun yang sama; kode lama disimpan di `legacy_code`.
 * - Akun baru dari COA revisi ditambahkan; akun header ditandai `is_header`.
 * - Akun aktif tanpa padanan & tanpa jurnal dinonaktifkan (tidak dihapus).
 * - Seluruh tabel accounts dicadangkan ke `accounts_backup_coa_2026` agar dapat dikembalikan.
 */
return new class extends Migration
{
    /** Kode lama (COA 4-digit) → kode baru (COA PSAK 2026). */
    private const MAP = [
        '1101' => '11101', '1102' => '11109', '1103' => '11110',
        '1201' => '11205', '1202' => '11204', '1209' => '11209',
        '1301' => '11701', '1302' => '11703',
        '1401' => '11601', '1402' => '11602', '1403' => '11603',
        '1501' => '11501', '1502' => '11502', '1503' => '11503',
        '1601' => '12104', '1609' => '12203', '1701' => '12401',
        '2101' => '21101', '2102' => '21103',
        '2201' => '21401', '2202' => '21402', '2203' => '21408', '2204' => '21403', '2205' => '21407',
        '2301' => '21503', '2302' => '21501', '2401' => '21303',
        '3101' => '31101', '3201' => '31301', '3301' => '31303',
        '4101' => '41202', '4102' => '41106', '4103' => '41201', '4201' => '71101',
        '5101' => '51301', '5201' => '51101', '5202' => '51201', '5203' => '51202',
        '5204' => '51204', '5205' => '51206', '5206' => '51207',
        '6101' => '61102', '6102' => '61201', '6103' => '61401', '6104' => '61301', '6105' => '61602',
        '6106' => '62001', '6107' => '61502', '6108' => '62003', '6110' => '62004', '6111' => '61604',
        '6112' => '61901', '6201' => '81101',
    ];

    private const BACKUP = 'accounts_backup_coa_2026';

    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'fs_group')) {
                $table->string('fs_group')->nullable()->after('account_type');
            }
            if (! Schema::hasColumn('accounts', 'is_header')) {
                $table->boolean('is_header')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('accounts', 'legacy_code')) {
                $table->string('legacy_code', 20)->nullable()->after('code');
            }
        });

        if (! Schema::hasTable(self::BACKUP)) {
            DB::statement('CREATE TABLE `' . self::BACKUP . '` AS SELECT * FROM `accounts`');
        }

        $rows = require database_path('data/coa_psak_2026.php');
        $codes = array_column($rows, 0);

        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            DB::transaction(function () use ($orgId, $rows, $codes) {
                $byCode = fn (string $c) => DB::table('accounts')->where('organization_id', $orgId)->where('code', $c)->first();

                // 1) Petakan akun lama ke kode baru (id dipertahankan).
                foreach (self::MAP as $old => $new) {
                    $acc = $byCode($old);
                    if ($acc && ! $byCode($new)) {
                        DB::table('accounts')->where('id', $acc->id)->update(['code' => $new, 'legacy_code' => $old]);
                    }
                }

                // 2) Selaraskan / tambahkan seluruh akun COA revisi.
                foreach ($rows as [$code, $name, $kelompok, $type, $normal, $report, $sub, $header]) {
                    $attrs = [
                        'name'           => $name,
                        'type'           => $type,
                        'account_type'   => $kelompok,
                        'fs_group'       => $sub,
                        'normal_balance' => $normal,
                        'report'         => $report,
                        'is_header'      => $header,
                        'is_active'      => true,
                        'updated_at'     => now(),
                    ];
                    if ($acc = $byCode($code)) {
                        DB::table('accounts')->where('id', $acc->id)->update($attrs);
                    } else {
                        DB::table('accounts')->insert($attrs + [
                            'id' => (string) Str::uuid(), 'organization_id' => $orgId, 'code' => $code, 'created_at' => now(),
                        ]);
                    }
                }

                // 3) Nonaktifkan akun aktif lain yang tidak ada di COA revisi & belum pernah dijurnal.
                DB::table('accounts')
                    ->where('organization_id', $orgId)
                    ->where('is_active', true)
                    ->whereNotIn('code', $codes)
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_lines')->whereColumn('journal_lines.account_id', 'accounts.id'))
                    ->update(['is_active' => false, 'updated_at' => now()]);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::BACKUP)) {
            return;
        }

        DB::transaction(function () {
            $backupIds = DB::table(self::BACKUP)->pluck('id');
            // Akun yang dibuat migrasi ini (tidak ada di cadangan & belum dijurnal) dihapus.
            DB::table('accounts')->whereNotIn('id', $backupIds)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_lines')->whereColumn('journal_lines.account_id', 'accounts.id'))
                ->delete();
            foreach (DB::table(self::BACKUP)->get() as $b) {
                DB::table('accounts')->where('id', $b->id)->update([
                    'code' => $b->code, 'name' => $b->name, 'type' => $b->type, 'account_type' => $b->account_type,
                    'fs_group' => $b->fs_group ?? null, 'normal_balance' => $b->normal_balance, 'report' => $b->report,
                    'parent_id' => $b->parent_id, 'is_active' => $b->is_active,
                ]);
            }
        });

        Schema::table('accounts', function (Blueprint $table) {
            foreach (['is_header', 'legacy_code'] as $col) {
                if (Schema::hasColumn('accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists(self::BACKUP);
    }
};
