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

        // [code, name, type, account_type (Control Account), normal_balance (Db/Kr), report (NRC/LR)]
        $accounts = [
            ['1-1100', 'Kas & Bank',                        'asset',     'Kas di Bank',                'Db', 'NRC'],
            ['1-1200', 'Piutang Usaha',                     'asset',     'Piutang Usaha',              'Db', 'NRC'],
            ['1-1300', 'Persediaan Biomassa',               'asset',     'Persediaan',                 'Db', 'NRC'],
            ['1-1400', 'PPN Dipungut Wapu (Clearing)',      'asset',     'Aset Lancar Lainnya',        'Db', 'NRC'],
            ['1-1500', 'Uang Muka & Biaya Dibayar Di Muka', 'asset',     'Aset Lancar Lainnya',        'Db', 'NRC'],
            ['1-2000', 'Aset Tetap',                        'asset',     'Aset Tetap',                 'Db', 'NRC'],
            ['2-2100', 'Utang PPN Keluaran',                'liability', 'Kewajiban Pajak',            'Kr', 'NRC'],
            ['2-2200', 'Utang Usaha',                       'liability', 'Utang Usaha',                'Kr', 'NRC'],
            ['2-2300', 'Utang Pajak Lainnya',               'liability', 'Kewajiban Pajak',            'Kr', 'NRC'],
            ['2-2400', 'Utang Pihak Berelasi',              'liability', 'Utang Usaha',                'Kr', 'NRC'],
            ['3-3000', 'Modal Disetor',                     'equity',    'Equity',                     'Kr', 'NRC'],
            ['3-3100', 'Laba Ditahan',                      'equity',    'Equity',                     'Kr', 'NRC'],
            ['4-4000', 'Pendapatan Penjualan Biomassa',     'revenue',   'Pendapatan Usaha',           'Kr', 'LR'],
            ['4-4100', 'Pendapatan Lainnya',                'revenue',   'Pendapatan Lain-lain',       'Kr', 'LR'],
            ['5-5000', 'Harga Pokok Penjualan (HPP)',       'expense',   'Harga Pokok Penjualan (HPP)','Db', 'LR'],
            ['5-5100', 'Beban Operasional',                 'expense',   'Beban Usaha',                'Db', 'LR'],
            ['5-5200', 'Beban Administrasi & Umum',         'expense',   'Beban Usaha',                'Db', 'LR'],
            ['5-5300', 'Beban Pajak',                       'expense',   'Beban Usaha',                'Db', 'LR'],
        ];

        foreach ($accounts as [$code, $name, $type, $accountType, $normal, $report]) {
            // Identitas akun tetap (firstOrCreate) — tidak menghapus/mengganti data lama.
            $account = Account::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'is_active' => true]
            );

            // Lengkapi metadata Control Account jika belum terisi (bersifat menambah).
            $patch = [];
            if (blank($account->account_type))   { $patch['account_type']   = $accountType; }
            if (blank($account->normal_balance)) { $patch['normal_balance'] = $normal; }
            if (blank($account->report))         { $patch['report']         = $report; }
            if ($patch) {
                $account->fill($patch)->save();
            }
        }
    }
}
