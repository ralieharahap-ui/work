<?php

namespace App\Services;

/**
 * Rekomendasi kode akun berbasis aturan kata kunci (Tahap 2 automasi PDF:
 * "AI account recommendation"). Ringan, deterministik, tanpa layanan luar —
 * memetakan teks keterangan/memo jurnal ke kandidat akun COA restated.
 *
 * Dipakai dua arah:
 *  - Server: suggest() untuk mengecek/menyarankan akun (mis. layer audit).
 *  - Klien: rules() dikirim ke halaman Jurnal agar pencocokan berjalan instan
 *    saat pengguna mengetik.
 */
class AccountRecommender
{
    /**
     * Aturan: [kode akun, sisi normal (debit/credit), daftar kata kunci].
     * Kata kunci dicocokkan sebagai substring pada teks yang sudah lowercase.
     */
    private function ruleset(): array
    {
        return [
            // Kas & Bank
            ['1101', 'debit',  ['kas kecil', 'petty cash', 'kas tunai', 'uang tunai']],
            ['1102', 'debit',  ['mandiri', 'giro', 'bank operasional', 'transfer bank', 'rekening operasional']],
            ['1103', 'debit',  ['tabungan', 'bank tabungan']],
            // Piutang
            ['1201', 'debit',  ['piutang trading']],
            ['1202', 'debit',  ['piutang biomassa', 'tagihan biomassa']],
            ['1203', 'debit',  ['piutang proyek', 'termin proyek', 'bast']],
            // Dibayar dimuka & uang muka
            ['1301', 'debit',  ['sewa dibayar dimuka', 'sewa dimuka']],
            ['1302', 'debit',  ['uang muka vendor', 'uang muka pembelian', 'down payment', 'dp vendor']],
            // Persediaan
            ['1401', 'debit',  ['persediaan biomassa', 'stok biomassa', 'stok cangkang']],
            ['1402', 'debit',  ['persediaan material', 'material proyek']],
            ['1403', 'debit',  ['atk', 'alat tulis', 'barang habis pakai']],
            // Pajak dibayar dimuka (aset)
            ['1501', 'debit',  ['ppn masukan', 'pajak masukan', 'faktur pajak masukan']],
            ['1502', 'debit',  ['pph 22 dibayar', 'pph22 dimuka', 'pph 22 dimuka']],
            ['1503', 'debit',  ['pph 23 dibayar', 'pph23 dimuka', 'pph 23 dimuka']],
            // Aset tetap
            ['1601', 'debit',  ['peralatan kantor', 'printer', 'komputer', 'laptop', 'mesin']],
            // Utang
            ['2101', 'credit', ['utang usaha', 'utang vendor', 'hutang vendor', 'bayar vendor', 'tagihan vendor']],
            ['2102', 'credit', ['subkontraktor', 'subkon', 'utang subkontraktor']],
            // Pajak terutang (liabilitas)
            ['2201', 'credit', ['ppn keluaran', 'pajak keluaran', 'faktur pajak keluaran']],
            ['2202', 'credit', ['pph 21', 'pph21']],
            ['2203', 'credit', ['pph 22 terutang']],
            ['2204', 'credit', ['pph 23', 'pph23']],
            ['2205', 'credit', ['pph final', 'pph 4(2)', 'pph 4 ayat 2', 'final 4(2)']],
            ['2301', 'credit', ['utang gaji']],
            ['2302', 'credit', ['bpjs', 'utang bpjs']],
            // Pendapatan
            ['4101', 'credit', ['pendapatan trading', 'penjualan trading']],
            ['4102', 'credit', ['penjualan biomassa', 'pendapatan biomassa', 'jual cangkang', 'penjualan cangkang']],
            ['4103', 'credit', ['pendapatan proyek', 'proyek lumpsum', 'lumpsum']],
            ['4201', 'credit', ['bunga bank', 'jasa giro', 'pendapatan bunga']],
            // HPP / COGS
            ['5101', 'debit',  ['hpp trading']],
            ['5201', 'debit',  ['pembelian biomassa', 'hpp biomassa', 'beli cangkang', 'beli biomassa']],
            ['5202', 'debit',  ['transport', 'ongkos angkut', 'angkutan', 'ekspedisi', 'freight', 'trucking']],
            ['5203', 'debit',  ['bongkar', 'muat', 'handling', 'bongkar muat']],
            ['5204', 'debit',  ['qc', 'sampling', 'uji lab', 'quality control', 'analisa lab']],
            ['5206', 'debit',  ['tenaga ahli biomassa']],
            ['5207', 'debit',  ['material proyek lumpsum']],
            ['5208', 'debit',  ['tenaga ahli proyek']],
            ['5299', 'debit',  ['penalti', 'klaim kontrak', 'denda kontrak']],
            // OPEX
            ['6101', 'debit',  ['gaji', 'thr', 'payroll', 'upah', 'honor']],
            ['6102', 'debit',  ['bpjs ketenagakerjaan', 'bpjs kesehatan', 'tunjangan', 'benefit karyawan']],
            ['6103', 'debit',  ['atk kantor', 'operasional kantor', 'keperluan kantor']],
            ['6104', 'debit',  ['sewa kantor', 'listrik', 'air', 'internet', 'utilitas', 'pln', 'telepon']],
            ['6105', 'debit',  ['legal', 'konsultan', 'notaris', 'pengacara', 'jasa profesional']],
            ['6106', 'debit',  ['biaya admin', 'biaya bank', 'administrasi bank', 'adm bank']],
            ['6107', 'debit',  ['perjalanan dinas', 'tiket pesawat', 'hotel', 'akomodasi', 'sppd']],
            ['6108', 'debit',  ['reimburse', 'reimbursement', 'penggantian biaya']],
            ['6110', 'debit',  ['membership', 'langganan', 'subscription', 'software', 'lisensi bulanan']],
            ['6111', 'debit',  ['sertifikasi', 'perizinan', 'izin usaha', 'csms']],
            ['6112', 'debit',  ['penyusutan', 'depresiasi', 'amortisasi']],
            ['6201', 'debit',  ['beban bunga', 'bunga pinjaman', 'denda pajak', 'sanksi pajak']],
        ];
    }

    /**
     * Ruleset ringkas untuk dikirim ke klien (pencocokan di sisi UI).
     * @return array<int,array{code:string,side:string,keywords:array<int,string>}>
     */
    public function rules(): array
    {
        return array_map(
            fn ($r) => ['code' => $r[0], 'side' => $r[1], 'keywords' => $r[2]],
            $this->ruleset()
        );
    }

    /**
     * Sarankan kode akun untuk sebuah teks (server-side).
     * @return array<int,array{code:string,side:string,score:int}>
     */
    public function suggest(string $text): array
    {
        $text = mb_strtolower($text);
        $hits = [];

        foreach ($this->ruleset() as [$code, $side, $keywords]) {
            $score = 0;
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($text, $kw)) {
                    // Kata kunci lebih panjang/spesifik diberi bobot lebih tinggi.
                    $score += 1 + (int) (mb_strlen($kw) >= 8);
                }
            }
            if ($score > 0) {
                $hits[] = ['code' => $code, 'side' => $side, 'score' => $score];
            }
        }

        usort($hits, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $hits;
    }
}
