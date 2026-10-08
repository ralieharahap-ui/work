<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * COA revisi PSAK 2026 (database/data/coa_psak_2026.php).
     * Hanya MENAMBAH akun yang belum ada (per kode) — akun & saldo yang sudah ada tidak diubah.
     * Pemetaan akun lama → kode baru dilakukan migrasi 2026_10_08_000002_revise_coa_psak_2026.
     */
    public function run(): void
    {
        $org  = Organization::where('slug', 'pt-gep')->firstOrFail();
        $rows = require database_path('data/coa_psak_2026.php');

        foreach ($rows as [$code, $name, $kelompok, $type, $normal, $report, $sub, $header]) {
            Account::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $code],
                [
                    'name'           => $name,
                    'type'           => $type,
                    'account_type'   => $kelompok,
                    'fs_group'       => $sub,
                    'normal_balance' => $normal,
                    'report'         => $report,
                    'is_header'      => $header,
                    'is_active'      => true,
                ]
            );
        }
    }
}
