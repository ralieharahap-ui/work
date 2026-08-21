<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Chart of Accounts PT GEP — selaras PSAK (SAK Indonesia).
 *
 * Struktur kode 4-digit:
 *   1xxx Aset · 2xxx Liabilitas · 3xxx Ekuitas · 4xxx Pendapatan
 *   5xxx Beban Pokok Penjualan · 6xxx Beban Usaha & Beban Lain-lain
 *
 * Kelompok FS (fs_group) mengikuti penyajian PSAK 1 (Laporan Posisi Keuangan):
 *   Aset Lancar · Aset Tidak Lancar · Liabilitas Jangka Pendek ·
 *   Liabilitas Jangka Panjang · Ekuitas · Pendapatan · Pendapatan Lain-lain ·
 *   Beban Pokok Penjualan · Beban Usaha · Beban Lain-lain
 *
 * Istilah PSAK: "Beban Pokok Penjualan" (bukan HPP), "Cadangan Kerugian
 * Penurunan Nilai" (PSAK 71/CKPN), "Utang Pajak", "Ekuitas", "Aset Takberwujud"
 * (PSAK 19), "Akumulasi Penyusutan" (PSAK 16).
 *
 * Kolom tiap baris:
 *   [kode, nama, type, fs_group (Kelompok FS), account_type (Control Account),
 *    normal_balance (Db/Kr), report (NRC=Neraca / LR=Laba Rugi)]
 *
 * Idempoten & menyelaraskan: identitas akun (code) via firstOrCreate, lalu
 * field klasifikasi/kontrol disinkronkan ke nilai kanonik PSAK pada tiap run
 * (memperbaiki record lama). Saldo & data jurnal tidak disentuh. Akun legacy
 * format lama ("1-1100") yang belum dipakai jurnal dinonaktifkan (reversible).
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

            // Selaraskan klasifikasi & control account ke standar PSAK (upsert).
            // Tidak mengubah is_active maupun data jurnal terkait.
            $account->fill([
                'name'           => $name,
                'type'           => $type,
                'fs_group'       => $fsGroup,
                'account_type'   => $accountType,
                'normal_balance' => $normal,
                'report'         => $report,
            ])->save();
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
            // Aset Lancar
            ['1101', 'Kas Kecil / Petty Cash',                   'asset', 'Aset Lancar', 'Kas',                                'Db', 'NRC'],
            ['1102', 'Bank Operasional - Mandiri Giro',          'asset', 'Aset Lancar', 'Kas di Bank',                        'Db', 'NRC'],
            ['1103', 'Bank Tabungan Bisnis',                     'asset', 'Aset Lancar', 'Kas di Bank',                        'Db', 'NRC'],
            ['1201', 'Piutang Usaha - Trading',                  'asset', 'Aset Lancar', 'Piutang Usaha',                      'Db', 'NRC'],
            ['1202', 'Piutang Usaha - Biomassa',                 'asset', 'Aset Lancar', 'Piutang Usaha',                      'Db', 'NRC'],
            ['1203', 'Piutang Usaha - Proyek Lumpsum',           'asset', 'Aset Lancar', 'Piutang Usaha',                      'Db', 'NRC'],
            ['1209', 'Cadangan Kerugian Penurunan Nilai Piutang','asset', 'Aset Lancar', 'Cadangan Kerugian Penurunan Nilai',  'Kr', 'NRC'],
            ['1301', 'Sewa Dibayar Dimuka',                      'asset', 'Aset Lancar', 'Beban Dibayar Dimuka',               'Db', 'NRC'],
            ['1302', 'Uang Muka Vendor',                         'asset', 'Aset Lancar', 'Uang Muka',                          'Db', 'NRC'],
            ['1401', 'Persediaan Biomassa',                      'asset', 'Aset Lancar', 'Persediaan',                         'Db', 'NRC'],
            ['1402', 'Persediaan Material Proyek',               'asset', 'Aset Lancar', 'Persediaan',                         'Db', 'NRC'],
            ['1403', 'Persediaan ATK / Barang Habis Pakai',      'asset', 'Aset Lancar', 'Persediaan',                         'Db', 'NRC'],
            ['1501', 'PPN Masukan Dapat Dikreditkan',            'asset', 'Aset Lancar', 'Pajak Dibayar Dimuka',               'Db', 'NRC'],
            ['1502', 'PPh 22 Dibayar Dimuka',                    'asset', 'Aset Lancar', 'Pajak Dibayar Dimuka',               'Db', 'NRC'],
            ['1503', 'PPh 23 Dibayar Dimuka',                    'asset', 'Aset Lancar', 'Pajak Dibayar Dimuka',               'Db', 'NRC'],
            // Aset Tidak Lancar
            ['1601', 'Peralatan Kantor',                         'asset', 'Aset Tidak Lancar', 'Aset Tetap',                   'Db', 'NRC'],
            ['1609', 'Akumulasi Penyusutan - Peralatan Kantor',  'asset', 'Aset Tidak Lancar', 'Akumulasi Penyusutan',        'Kr', 'NRC'],
            ['1701', 'Lisensi / Aset Takberwujud',               'asset', 'Aset Tidak Lancar', 'Aset Takberwujud',            'Db', 'NRC'],

            // ── 2xxx LIABILITAS ──────────────────────────────────────────
            // Liabilitas Jangka Pendek
            ['2101', 'Utang Usaha - Vendor',                     'liability', 'Liabilitas Jangka Pendek',  'Utang Usaha',                      'Kr', 'NRC'],
            ['2102', 'Utang Subkontraktor',                      'liability', 'Liabilitas Jangka Pendek',  'Utang Usaha',                      'Kr', 'NRC'],
            ['2201', 'PPN Keluaran',                             'liability', 'Liabilitas Jangka Pendek',  'Utang Pajak',                      'Kr', 'NRC'],
            ['2202', 'PPh 21 Terutang',                          'liability', 'Liabilitas Jangka Pendek',  'Utang Pajak',                      'Kr', 'NRC'],
            ['2203', 'PPh 22 Terutang',                          'liability', 'Liabilitas Jangka Pendek',  'Utang Pajak',                      'Kr', 'NRC'],
            ['2204', 'PPh 23 Terutang',                          'liability', 'Liabilitas Jangka Pendek',  'Utang Pajak',                      'Kr', 'NRC'],
            ['2205', 'PPh Final 4(2) Terutang',                  'liability', 'Liabilitas Jangka Pendek',  'Utang Pajak',                      'Kr', 'NRC'],
            ['2301', 'Utang Gaji',                               'liability', 'Liabilitas Jangka Pendek',  'Beban Masih Harus Dibayar',        'Kr', 'NRC'],
            ['2302', 'Utang BPJS',                               'liability', 'Liabilitas Jangka Pendek',  'Beban Masih Harus Dibayar',        'Kr', 'NRC'],
            // Liabilitas Jangka Panjang
            ['2401', 'Utang Pihak Berelasi - Direksi',           'liability', 'Liabilitas Jangka Panjang', 'Utang Pihak Berelasi',             'Kr', 'NRC'],

            // ── 3xxx EKUITAS ─────────────────────────────────────────────
            ['3101', 'Modal Disetor',                            'equity', 'Ekuitas', 'Ekuitas', 'Kr', 'NRC'],
            ['3201', 'Saldo Laba',                               'equity', 'Ekuitas', 'Ekuitas', 'Kr', 'NRC'],
            ['3301', 'Dividen',                                  'equity', 'Ekuitas', 'Ekuitas', 'Db', 'NRC'],

            // ── 4xxx PENDAPATAN ──────────────────────────────────────────
            ['4101', 'Pendapatan Trading',                       'revenue', 'Pendapatan',            'Pendapatan Usaha',     'Kr', 'LR'],
            ['4102', 'Pendapatan Biomassa',                      'revenue', 'Pendapatan',            'Pendapatan Usaha',     'Kr', 'LR'],
            ['4103', 'Pendapatan Proyek Lumpsum',                'revenue', 'Pendapatan',            'Pendapatan Usaha',     'Kr', 'LR'],
            ['4201', 'Pendapatan Bunga Bank',                    'revenue', 'Pendapatan Lain-lain',  'Pendapatan Lain-lain', 'Kr', 'LR'],

            // ── 5xxx BEBAN POKOK PENJUALAN ───────────────────────────────
            ['5101', 'Beban Pokok Penjualan - Trading',          'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5201', 'Beban Pokok Penjualan - Biomassa',         'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5202', 'Transport Biomassa',                       'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5203', 'Handling / Bongkar Muat Biomassa',         'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5204', 'QC & Sampling Biomassa',                   'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5205', 'Administrasi Biomassa / Direct Project Expense', 'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5206', 'Tenaga Ahli Biomassa',                     'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5207', 'Material Proyek Lumpsum',                  'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5208', 'Tenaga Ahli Proyek Lumpsum',               'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5209', 'Administrasi Proyek Lumpsum',              'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],
            ['5299', 'Penalti / Klaim Kontrak',                  'expense', 'Beban Pokok Penjualan', 'Beban Pokok Penjualan', 'Db', 'LR'],

            // ── 6xxx BEBAN USAHA & BEBAN LAIN-LAIN ───────────────────────
            ['6101', 'Beban Gaji & THR',                         'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6102', 'Beban BPJS & Benefit',                     'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6103', 'Beban ATK & Operasional Kantor',           'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6104', 'Beban Sewa & Utilitas',                    'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6105', 'Beban Legal & Konsultan',                  'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6106', 'Beban Administrasi & Bank',                'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6107', 'Beban Rapat & Perjalanan Dinas Kantor',    'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6108', 'Beban Reimbursement Kantor',               'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6110', 'Beban Membership & Subscription',          'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6111', 'Beban Sertifikasi & Perizinan',            'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6112', 'Beban Penyusutan',                         'expense', 'Beban Usaha',      'Beban Usaha',       'Db', 'LR'],
            ['6201', 'Beban Bunga & Administrasi Bank',          'expense', 'Beban Lain-lain',  'Beban Lain-lain',   'Db', 'LR'],
        ];
    }
}
