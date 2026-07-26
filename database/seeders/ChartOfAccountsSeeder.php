<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'pt-gep')->firstOrFail();

        $accounts = [
            ['1-1100', 'Kas & Bank',                  'asset'],
            ['1-1200', 'Piutang Usaha',               'asset'],
            ['1-1300', 'Persediaan Biomassa',         'asset'],
            ['1-1400', 'PPN Dipungut Wapu (Clearing)','asset'],
            ['1-1500', 'Uang Muka & Biaya Dibayar Di Muka','asset'],
            ['1-2000', 'Aset Tetap',                  'asset'],
            ['2-2100', 'Utang PPN Keluaran',          'liability'],
            ['2-2200', 'Utang Usaha',                 'liability'],
            ['2-2300', 'Utang Pajak Lainnya',         'liability'],
            ['3-3000', 'Modal Disetor',               'equity'],
            ['3-3100', 'Laba Ditahan',                'equity'],
            ['4-4000', 'Pendapatan Penjualan Biomassa','revenue'],
            ['4-4100', 'Pendapatan Lainnya',          'revenue'],
            ['5-5000', 'Harga Pokok Penjualan (HPP)', 'expense'],
            ['5-5100', 'Beban Operasional',           'expense'],
            ['5-5200', 'Beban Administrasi & Umum',   'expense'],
            ['5-5300', 'Beban Pajak',                 'expense'],
        ];

        foreach ($accounts as [$code, $name, $type]) {
            Account::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'is_active' => true]
            );
        }
    }
}
