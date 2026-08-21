<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Chart of Accounts — Restated / Target COA PT GEP (revisi 2026).
 *
 * Struktur kode 4-digit:
 *   1xxx Aset · 2xxx Liabilitas · 3xxx Ekuitas · 4xxx Pendapatan
 *   5xxx HPP/COGS · 6xxx Beban (OPEX & Lain)
 *
 * Kolom tiap baris:
 *   [kode, nama, type, fs_group (Kelompok FS), account_type (Control Account),
 *    normal_balance (Db/Kr), report (NRC=Neraca / LR=Laba Rugi)]
 *
 * Idempoten: firstOrCreate + patch metadata yang masih kosong — tidak menimpa
 * data yang sudah diubah pengguna. Akun legacy format lama (mis. "1-1100")
 * yang belum pernah dipakai di jurnal dinonaktifkan (is_active=false) agar COA
 * tampil bersih tanpa menghapus apa pun (reversible).
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'pt-gep')->firstOrFail();

        foreach ($this->accounts() as [$code, $name, $type, $fsGroup, $accountType, $normal, $report]) {
            $account = Account::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'is_active' => true]
            );

            // Lengkapi metadata bila masih kosong (bersifat menambah, tidak menimpa).
            $patch = [];
            if (blank($account->account_type))   { $patch['account_type']   = $accountType; }
            if (blank($account->fs_group))        { $patch['fs_group']       = $fsGroup; }
            if (blank($account->normal_balance))  { $patch['normal_balance'] = $normal; }
            if (blank($account->report))          { $patch['report']         = $report; }
            if ($patch) {
                $account->fill($patch)->save();
            }
        }

        $this->retireLegacyAccounts($org->id);
    }

    /**
     * Nonaktifkan akun COA format lama ("X-XXXX") yang belum dipakai di jurnal.
     * Tidak dihapus agar tetap bisa dipulihkan bila diperlukan.
     */
    private function retireLegacyAccounts(string $orgId): void
    {
        Account::where('organization_id', $orgId)
            ->where('code', 'like', '_-%')       // pola "1-1100", "2-2100", dst
            ->where('is_active', true)
            ->whereDoesntHave('lines')
            ->update(['is_active' => false]);
    }

    private function accounts(): array
    {
        return [
            // ── 1xxx ASET ────────────────────────────────────────────────
            ['1101', 'Kas Kecil / Petty Cash',                   'asset', 'Aset Lancar',            'Kas',                                  'Db', 'NRC'],
            ['1102', 'Bank Operasional - Mandiri Giro',          'asset', 'Aset Lancar',            'Kas di Bank',                          'Db', 'NRC'],
            ['1103', 'Bank Tabungan Bisnis',                     'asset', 'Aset Lancar',            'Kas di Bank',                          'Db', 'NRC'],
            ['1201', 'Piutang Usaha - Trading',                  'asset', 'Aset Lancar',            'Piutang Usaha',                        'Db', 'NRC'],
            ['1202', 'Piutang Usaha - Biomassa',                 'asset', 'Aset Lancar',            'Piutang Usaha',                        'Db', 'NRC'],
            ['1203', 'Piutang Usaha - Proyek Lumpsum',           'asset', 'Aset Lancar',            'Piutang Usaha',                        'Db', 'NRC'],
            ['1209', 'Cadangan Kerugian Piutang',                'asset', 'Aset Kontra',            'Cadangan Kerugian Piutang',            'Kr', 'NRC'],
            ['1301', 'Sewa Dibayar Dimuka',                      'asset', 'Aset Lancar',            'Aset Lancar Lainnya',                  'Db', 'NRC'],
            ['1302', 'Uang Muka Vendor',                         'asset', 'Aset Lancar',            'Aset Lancar Lainnya',                  'Db', 'NRC'],
            ['1401', 'Persediaan Biomassa',                      'asset', 'Persediaan',             'Persediaan',                           'Db', 'NRC'],
            ['1402', 'Persediaan Material Proyek',               'asset', 'Persediaan',             'Persediaan',                           'Db', 'NRC'],
            ['1403', 'Persediaan ATK / Barang Habis Pakai',      'asset', 'Persediaan',             'Persediaan',                           'Db', 'NRC'],
            ['1501', 'PPN Masukan Dapat Dikreditkan',            'asset', 'Pajak',                  'PPN Masukan',                          'Db', 'NRC'],
            ['1502', 'PPh 22 Dibayar Dimuka',                    'asset', 'Pajak',                  'Pajak Dibayar Dimuka',                 'Db', 'NRC'],
            ['1503', 'PPh 23 Dibayar Dimuka',                    'asset', 'Pajak',                  'Pajak Dibayar Dimuka',                 'Db', 'NRC'],
            ['1601', 'Peralatan Kantor',                         'asset', 'Aset Tetap',             'Aset Tetap',                           'Db', 'NRC'],
            ['1609', 'Akumulasi Penyusutan - Peralatan Kantor',  'asset', 'Aset Tetap Kontra',      'Aset Tetap (Kontra/Akum. Penyusutan)', 'Kr', 'NRC'],
            ['1701', 'Lisensi/Aset Takberwujud',                 'asset', 'Aset Takberwujud',       'Aset Lain-lain',                       'Db', 'NRC'],

            // ── 2xxx LIABILITAS ──────────────────────────────────────────
            ['2101', 'Utang Usaha - Vendor',                     'liability', 'Liabilitas Lancar',        'Utang Usaha',                       'Kr', 'NRC'],
            ['2102', 'Utang Subkontraktor',                      'liability', 'Liabilitas Lancar',        'Utang Usaha',                       'Kr', 'NRC'],
            ['2201', 'PPN Keluaran',                             'liability', 'Liabilitas Pajak',         'Kewajiban Pajak',                   'Kr', 'NRC'],
            ['2202', 'PPh 21 Terutang',                          'liability', 'Liabilitas Pajak',         'Kewajiban Pajak',                   'Kr', 'NRC'],
            ['2203', 'PPh 22 Terutang',                          'liability', 'Liabilitas Pajak',         'Kewajiban Pajak',                   'Kr', 'NRC'],
            ['2204', 'PPh 23 Terutang',                          'liability', 'Liabilitas Pajak',         'Kewajiban Pajak',                   'Kr', 'NRC'],
            ['2205', 'PPh Final 4(2) Terutang',                  'liability', 'Liabilitas Pajak',         'Kewajiban Pajak',                   'Kr', 'NRC'],
            ['2301', 'Utang Gaji',                               'liability', 'Liabilitas Lancar',        'Liabilitas Jangka Pendek Lainnya',  'Kr', 'NRC'],
            ['2302', 'Utang BPJS',                               'liability', 'Liabilitas Lancar',        'Liabilitas Jangka Pendek Lainnya',  'Kr', 'NRC'],
            ['2401', 'Utang Pihak Berelasi - Direksi',           'liability', 'Liabilitas Lancar/Panjang','Liabilitas Jangka Panjang Lainnya', 'Kr', 'NRC'],

            // ── 3xxx EKUITAS ─────────────────────────────────────────────
            ['3101', 'Modal Disetor',                            'equity', 'Ekuitas', 'Equity', 'Kr', 'NRC'],
            ['3201', 'Saldo Laba',                               'equity', 'Ekuitas', 'Equity', 'Kr', 'NRC'],
            ['3301', 'Dividen',                                  'equity', 'Ekuitas', 'Equity', 'Db', 'NRC'],

            // ── 4xxx PENDAPATAN ──────────────────────────────────────────
            ['4101', 'Pendapatan Trading',                       'revenue', 'Pendapatan',      'Pendapatan Usaha',     'Kr', 'LR'],
            ['4102', 'Pendapatan Biomassa',                      'revenue', 'Pendapatan',      'Pendapatan Usaha',     'Kr', 'LR'],
            ['4103', 'Pendapatan Proyek Lumpsum',                'revenue', 'Pendapatan',      'Pendapatan Usaha',     'Kr', 'LR'],
            ['4201', 'Pendapatan Bunga Bank',                    'revenue', 'Pendapatan Lain', 'Pendapatan Lain-lain', 'Kr', 'LR'],

            // ── 5xxx HPP / COGS ──────────────────────────────────────────
            ['5101', 'HPP Trading',                              'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5201', 'HPP/Pembelian Biomassa',                   'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5202', 'Transport Biomassa',                       'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5203', 'Handling/Bongkar Muat Biomassa',           'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5204', 'QC & Sampling Biomassa',                   'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5205', 'Administrasi Biomassa / Direct Project Expense', 'expense', 'COGS',          'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5206', 'Tenaga Ahli Biomassa',                     'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5207', 'Material Proyek Lumpsum',                  'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5208', 'Tenaga Ahli Proyek Lumpsum',               'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5209', 'Administrasi Proyek Lumpsum',              'expense', 'COGS',               'Harga Pokok Penjualan', 'Db', 'LR'],
            ['5299', 'Penalti / Klaim Kontrak',                  'expense', 'COGS / Contract Cost','Harga Pokok Penjualan', 'Db', 'LR'],

            // ── 6xxx BEBAN (OPEX & LAIN) ─────────────────────────────────
            ['6101', 'Gaji & THR',                               'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6102', 'BPJS & Benefit',                           'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6103', 'ATK & Operasional Kantor',                 'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6104', 'Sewa & Utilitas',                          'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6105', 'Legal & Konsultan',                        'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6106', 'Beban Administrasi & Bank',                'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6107', 'Rapat & Perjalanan Dinas Kantor',          'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6108', 'Reimbursement Kantor',                     'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6110', 'Membership & Subscription',                'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6111', 'Sertifikasi & Perizinan',                  'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6112', 'Beban Penyusutan',                         'expense', 'OPEX',       'Beban Usaha',           'Db', 'LR'],
            ['6201', 'Beban Pajak/Bunga Bank',                   'expense', 'Beban Lain', 'Beban Non-operasional', 'Db', 'LR'],
        ];
    }
}
