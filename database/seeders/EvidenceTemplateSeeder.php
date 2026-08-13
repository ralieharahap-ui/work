<?php

namespace Database\Seeders;

use App\Models\EvidenceTemplate;
use Illuminate\Database\Seeder;

/**
 * Template dokumen & kertas kerja bawaan sistem.
 *
 * Seeder ini idempoten (dicocokkan lewat `code`) dan selalu dijalankan ulang
 * saat deploy, sehingga perbaikan pada template bawaan ikut terkirim ke
 * instalasi yang sudah berjalan. Template buatan pengguna tidak tersentuh
 * karena selalu punya `organization_id` dan `is_system = false`.
 */
class EvidenceTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([...$this->templates(), ...$this->businessTemplates()] as $template) {
            EvidenceTemplate::updateOrCreate(
                ['code' => $template['code']],
                $template + ['organization_id' => null, 'is_system' => true, 'is_active' => true],
            );
        }
    }

    /** Dokumen internal: berita acara, kertas kerja, laporan, checklist. */
    private function templates(): array
    {
        return [
            [
                'code'        => 'BAP',
                'name'        => 'Berita Acara Penyelesaian Pekerjaan',
                'category'    => 'Berita Acara',
                'description' => 'Dokumen resmi yang menyatakan sebuah pekerjaan telah selesai dikerjakan dan diserahkan.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'lokasi',    'label' => 'Lokasi Pekerjaan', 'type' => 'text', 'placeholder' => 'Cth: Kantor Pusat Pekanbaru'],
                    ['key' => 'periode',   'label' => 'Periode Pelaksanaan', 'type' => 'text', 'placeholder' => 'Cth: 1 – 31 Agustus 2026'],
                    ['key' => 'referensi', 'label' => 'Nomor Referensi / SPK', 'type' => 'text'],
                ],
                'body_html' => <<<'HTML'
<p>Pada hari ini, {{today.long}}, bertempat di kantor {{org.name}}, yang bertanda tangan di bawah ini menyatakan bahwa pekerjaan berikut telah <strong>SELESAI DILAKSANAKAN</strong>:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 32%"><strong>Uraian Pekerjaan</strong></td><td>{{task.title}}</td></tr>
  <tr><td><strong>Kategori</strong></td><td>{{task.category}}</td></tr>
  <tr><td><strong>Proyek Terkait</strong></td><td>{{project.title}}</td></tr>
  <tr><td><strong>Penanggung Jawab</strong></td><td>{{pic.name}}</td></tr>
  <tr><td><strong>Divisi</strong></td><td>{{division.name}}</td></tr>
  <tr><td><strong>Tenggat Waktu</strong></td><td>{{task.deadline}}</td></tr>
</table>
<p><strong>A. Uraian Hasil Pekerjaan</strong></p>
<p>{{task.description}}</p>
<p><strong>B. Rincian Tahapan yang Diselesaikan</strong></p>
{{task.checklist}}
<p><strong>C. Kesimpulan</strong></p>
<p>Seluruh lingkup pekerjaan sebagaimana tersebut di atas telah dilaksanakan sesuai ketentuan dan dapat diterima. Berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>
HTML,
            ],

            [
                'code'        => 'KKP',
                'name'        => 'Kertas Kerja Pemeriksaan',
                'category'    => 'Kertas Kerja',
                'description' => 'Lembar kerja untuk mencatat langkah pemeriksaan, temuan, dan simpulan atas sebuah pekerjaan.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'periode',   'label' => 'Periode Pemeriksaan', 'type' => 'text'],
                    ['key' => 'penyusun',  'label' => 'Disusun Oleh', 'type' => 'text'],
                    ['key' => 'direview',  'label' => 'Direview Oleh', 'type' => 'text'],
                ],
                'body_html' => <<<'HTML'
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 32%"><strong>Objek Pemeriksaan</strong></td><td>{{task.title}}</td></tr>
  <tr><td><strong>Unit / Divisi</strong></td><td>{{division.name}}</td></tr>
  <tr><td><strong>Pelaksana</strong></td><td>{{pic.name}}</td></tr>
  <tr><td><strong>Tanggal Kertas Kerja</strong></td><td>{{today.long}}</td></tr>
</table>
<p><strong>1. Tujuan Pemeriksaan</strong></p>
<p>Memastikan pekerjaan <em>{{task.title}}</em> telah dilaksanakan sesuai prosedur dan menghasilkan keluaran yang dapat dipertanggungjawabkan.</p>
<p><strong>2. Prosedur &amp; Hasil</strong></p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 34%">Prosedur / Langkah Kerja</th>
    <th style="width: 30%">Bukti / Dokumen Pendukung</th>
    <th style="width: 30%">Hasil &amp; Catatan</th>
  </tr>
  <tr><td>1</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>3</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>4</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>5</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
<p><strong>3. Temuan</strong></p>
<p>&nbsp;</p>
<p><strong>4. Simpulan</strong></p>
<p>&nbsp;</p>
<p><strong>5. Rekomendasi / Tindak Lanjut</strong></p>
<p>&nbsp;</p>
HTML,
            ],

            [
                'code'        => 'LPT',
                'name'        => 'Laporan Pelaksanaan Tugas',
                'category'    => 'Laporan',
                'description' => 'Laporan naratif atas pelaksanaan sebuah tugas beserta kendala dan tindak lanjutnya.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'periode', 'label' => 'Periode Pelaporan', 'type' => 'text'],
                    ['key' => 'lokasi',  'label' => 'Lokasi Pelaksanaan', 'type' => 'text'],
                ],
                'body_html' => <<<'HTML'
<p><strong>I. PENDAHULUAN</strong></p>
<p>Laporan ini disusun sebagai pertanggungjawaban atas pelaksanaan tugas <strong>{{task.title}}</strong> yang menjadi tanggung jawab {{pic.name}} pada divisi {{division.name}}, dengan tenggat {{task.deadline}}.</p>
<p><strong>II. RUANG LINGKUP</strong></p>
<p>{{task.description}}</p>
<p><strong>III. PELAKSANAAN</strong></p>
{{task.checklist}}
<p><strong>IV. KENDALA YANG DIHADAPI</strong></p>
<p>&nbsp;</p>
<p><strong>V. TINDAK LANJUT</strong></p>
<p>&nbsp;</p>
<p><strong>VI. PENUTUP</strong></p>
<p>Demikian laporan ini dibuat untuk menjadi bahan evaluasi dan arsip perusahaan.</p>
HTML,
            ],

            [
                'code'        => 'CKL',
                'name'        => 'Daftar Simak (Checklist) Verifikasi',
                'category'    => 'Checklist',
                'description' => 'Lembar verifikasi bertahap untuk memastikan seluruh syarat penyelesaian tugas terpenuhi.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'verifikator', 'label' => 'Verifikator', 'type' => 'text'],
                ],
                'body_html' => <<<'HTML'
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 32%"><strong>Tugas</strong></td><td>{{task.title}}</td></tr>
  <tr><td><strong>PIC</strong></td><td>{{pic.name}}</td></tr>
  <tr><td><strong>Tanggal Verifikasi</strong></td><td>{{today.long}}</td></tr>
</table>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 48%">Item yang Diperiksa</th>
    <th style="width: 10%">Ya</th>
    <th style="width: 10%">Tidak</th>
    <th style="width: 26%">Keterangan</th>
  </tr>
  <tr><td>1</td><td>Seluruh sub-task telah diselesaikan</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>Keluaran pekerjaan sesuai permintaan</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>3</td><td>Dokumen pendukung telah dilampirkan</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>4</td><td>Tidak ada temuan yang belum ditindaklanjuti</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>5</td><td>Pekerjaan diselesaikan sebelum tenggat ({{task.deadline}})</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
<p><strong>Catatan Verifikator</strong></p>
<p>&nbsp;</p>
HTML,
            ],

            [
                'code'        => 'BAS',
                'name'        => 'Berita Acara Serah Terima Dokumen',
                'category'    => 'Serah Terima',
                'description' => 'Bukti serah terima dokumen atau barang antara pihak yang menyerahkan dan yang menerima.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'penerima',       'label' => 'Nama Penerima', 'type' => 'text'],
                    ['key' => 'jabatan_terima', 'label' => 'Jabatan Penerima', 'type' => 'text'],
                ],
                'body_html' => <<<'HTML'
<p>Pada hari ini, {{today.long}}, telah dilakukan serah terima dengan rincian sebagai berikut:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 32%"><strong>Yang Menyerahkan</strong></td><td>{{pic.name}} — {{division.name}}</td></tr>
  <tr><td><strong>Dasar Penyerahan</strong></td><td>{{task.title}}</td></tr>
  <tr><td><strong>Proyek Terkait</strong></td><td>{{project.title}}</td></tr>
</table>
<p><strong>Daftar Dokumen / Barang yang Diserahkan</strong></p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 46%">Nama Dokumen / Barang</th>
    <th style="width: 14%">Jumlah</th>
    <th style="width: 34%">Keterangan</th>
  </tr>
  <tr><td>1</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>3</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
<p>Dokumen/barang tersebut di atas telah diterima dalam keadaan baik dan lengkap. Berita acara ini dibuat rangkap dua dengan kekuatan hukum yang sama.</p>
HTML,
            ],

            [
                'code'        => 'KKM',
                'name'        => 'Kertas Kerja Monitoring Progres',
                'category'    => 'Kertas Kerja',
                'description' => 'Lembar pemantauan progres mingguan sebuah pekerjaan beserta hambatan dan rencana tindak lanjut.',
                'orientation' => 'landscape',
                'fields'      => [
                    ['key' => 'periode', 'label' => 'Periode Monitoring', 'type' => 'text'],
                ],
                'body_html' => <<<'HTML'
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 20%"><strong>Pekerjaan</strong></td><td>{{task.title}}</td>
      <td style="width: 14%"><strong>PIC</strong></td><td>{{pic.name}}</td></tr>
  <tr><td><strong>Proyek</strong></td><td>{{project.title}}</td>
      <td><strong>Tenggat</strong></td><td>{{task.deadline}}</td></tr>
</table>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">Minggu</th>
    <th style="width: 26%">Rencana Kerja</th>
    <th style="width: 26%">Realisasi</th>
    <th style="width: 10%">Progres (%)</th>
    <th style="width: 16%">Hambatan</th>
    <th style="width: 16%">Tindak Lanjut</th>
  </tr>
  <tr><td>I</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>II</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>III</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>IV</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
<p><strong>Simpulan Akhir Periode</strong></p>
<p>&nbsp;</p>
HTML,
            ],
        ];
    }

    /**
     * Dokumen bisnis yang lazim dipakai sebagai bukti penyelesaian tugas:
     * kontrak, pengadaan (penawaran/PO/DO), keuangan (invoice/permintaan
     * pembayaran), serah terima, dan surat keluar resmi.
     *
     * Angka, nama pihak, dan nilai transaksi sengaja tidak diisi otomatis —
     * data itu tidak ada di dalam task, jadi disediakan sebagai kolom isian
     * (`fields`) atau baris tabel kosong yang dilengkapi pengguna.
     */
    private function businessTemplates(): array
    {
        return [
            [
                'code'        => 'KTR',
                'name'        => 'Kontrak / Perjanjian Kerja Sama',
                'category'    => 'Kontrak',
                'description' => 'Perjanjian kerja sama antara perusahaan dan mitra, memuat lingkup, nilai, jangka waktu, dan ketentuan pembayaran.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'pihak_kedua',   'label' => 'Nama Pihak Kedua', 'type' => 'text', 'placeholder' => 'Cth: PT Mitra Sawit Lestari'],
                    ['key' => 'wakil_kedua',   'label' => 'Diwakili Oleh (Pihak Kedua)', 'type' => 'text'],
                    ['key' => 'jabatan_kedua', 'label' => 'Jabatan Wakil Pihak Kedua', 'type' => 'text'],
                    ['key' => 'alamat_kedua',  'label' => 'Alamat Pihak Kedua', 'type' => 'text'],
                    ['key' => 'nilai',         'label' => 'Nilai Kontrak (Rp)', 'type' => 'text'],
                    ['key' => 'jangka_waktu',  'label' => 'Jangka Waktu', 'type' => 'text', 'placeholder' => 'Cth: 1 Januari - 31 Desember 2026'],
                ],
                'body_html' => <<<'HTML'
<p>Pada hari ini, {{today.long}}, yang bertanda tangan di bawah ini:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 18%"><strong>PIHAK PERTAMA</strong></td>
      <td>{{org.name}}, diwakili oleh <strong>{{creator.name}}</strong>, selanjutnya disebut <strong>PIHAK PERTAMA</strong>.</td></tr>
  <tr><td><strong>PIHAK KEDUA</strong></td>
      <td>Sebagaimana tercantum pada kolom isian di atas, selanjutnya disebut <strong>PIHAK KEDUA</strong>.</td></tr>
</table>
<p>Kedua belah pihak sepakat mengikatkan diri dalam perjanjian kerja sama dengan ketentuan sebagai berikut:</p>
<p><strong>Pasal 1 — Maksud dan Tujuan</strong></p>
<p>Perjanjian ini mengatur pelaksanaan pekerjaan <strong>{{task.title}}</strong> sebagaimana diuraikan berikut:</p>
<p>{{task.description}}</p>
<p><strong>Pasal 2 — Lingkup Pekerjaan</strong></p>
{{task.checklist}}
<p><strong>Pasal 3 — Jangka Waktu</strong></p>
<p>Pekerjaan dilaksanakan sesuai jangka waktu pada kolom isian, dengan target penyelesaian paling lambat {{task.deadline}}.</p>
<p><strong>Pasal 4 — Nilai dan Cara Pembayaran</strong></p>
<p>Nilai perjanjian sebagaimana tercantum pada kolom isian, sudah termasuk pajak yang berlaku. Pembayaran dilakukan setelah pekerjaan diterima baik dan dituangkan dalam Berita Acara Serah Terima.</p>
<p><strong>Pasal 5 — Hak dan Kewajiban</strong></p>
<p>PIHAK PERTAMA berhak menerima hasil pekerjaan sesuai spesifikasi dan berkewajiban melakukan pembayaran sesuai Pasal 4. PIHAK KEDUA berkewajiban menyelesaikan pekerjaan tepat waktu dan berhak menerima pembayaran atas pekerjaan yang telah diterima baik.</p>
<p><strong>Pasal 6 — Sanksi Keterlambatan</strong></p>
<p>Keterlambatan penyelesaian pekerjaan dikenakan denda sebesar 1&permil; (satu per mil) per hari kalender dari nilai perjanjian, dengan denda maksimal 5% (lima persen) dari nilai perjanjian.</p>
<p><strong>Pasal 7 — Penyelesaian Perselisihan</strong></p>
<p>Perselisihan diselesaikan secara musyawarah. Apabila tidak tercapai mufakat, kedua belah pihak sepakat menyelesaikannya melalui Pengadilan Negeri yang berwenang.</p>
<p><strong>Pasal 8 — Penutup</strong></p>
<p>Perjanjian ini dibuat rangkap dua bermeterai cukup, masing-masing mempunyai kekuatan hukum yang sama, dan berlaku sejak ditandatangani kedua belah pihak.</p>
HTML,
            ],

            [
                'code'        => 'PNW',
                'name'        => 'Surat Penawaran Harga',
                'category'    => 'Pengadaan',
                'description' => 'Penawaran harga barang atau jasa kepada calon pembeli, lengkap dengan rincian item dan syarat penawaran.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'kepada',       'label' => 'Ditujukan Kepada', 'type' => 'text', 'placeholder' => 'Cth: PT PLN Nusantara Power'],
                    ['key' => 'up',           'label' => 'U.p. (Nama & Jabatan)', 'type' => 'text'],
                    ['key' => 'alamat',       'label' => 'Alamat Tujuan', 'type' => 'text'],
                    ['key' => 'masa_berlaku', 'label' => 'Masa Berlaku Penawaran', 'type' => 'text', 'placeholder' => 'Cth: 30 hari kalender'],
                ],
                'body_html' => <<<'HTML'
<p>Dengan hormat,</p>
<p>Sehubungan dengan permintaan penawaran atas <strong>{{task.title}}</strong>, bersama ini kami sampaikan penawaran harga sebagai berikut:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 34%">Uraian Barang / Jasa</th>
    <th style="width: 10%">Qty</th>
    <th style="width: 10%">Satuan</th>
    <th style="width: 20%">Harga Satuan (Rp)</th>
    <th style="width: 20%">Jumlah (Rp)</th>
  </tr>
  <tr><td>1</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>3</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td colspan="5" style="text-align: right"><strong>Subtotal</strong></td><td>&nbsp;</td></tr>
  <tr><td colspan="5" style="text-align: right"><strong>PPN 11%</strong></td><td>&nbsp;</td></tr>
  <tr><td colspan="5" style="text-align: right"><strong>Total</strong></td><td>&nbsp;</td></tr>
</table>
<p><strong>Syarat dan Ketentuan</strong></p>
<ol>
  <li>Harga sudah termasuk PPN dan biaya pengiriman sampai lokasi tujuan.</li>
  <li>Masa berlaku penawaran sesuai kolom isian di atas.</li>
  <li>Waktu pengiriman disepakati setelah Purchase Order diterima.</li>
  <li>Pembayaran dilakukan sesuai kesepakatan kedua belah pihak.</li>
</ol>
<p>Demikian penawaran ini kami sampaikan. Atas perhatian dan kerja samanya kami ucapkan terima kasih.</p>
HTML,
            ],

            [
                'code'        => 'PRO',
                'name'        => 'Purchase Order (PO)',
                'category'    => 'Pengadaan',
                'description' => 'Surat pesanan resmi kepada pemasok, memuat rincian barang/jasa, harga, dan syarat penyerahan.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'pemasok',      'label' => 'Nama Pemasok / Vendor', 'type' => 'text'],
                    ['key' => 'alamat',       'label' => 'Alamat Pemasok', 'type' => 'text'],
                    ['key' => 'kontak',       'label' => 'Kontak Pemasok', 'type' => 'text'],
                    ['key' => 'ref_penawaran', 'label' => 'Nomor Referensi Penawaran', 'type' => 'text'],
                    ['key' => 'tgl_kirim',    'label' => 'Tanggal Pengiriman Diminta', 'type' => 'date'],
                    ['key' => 'termin',       'label' => 'Termin Pembayaran', 'type' => 'text', 'placeholder' => 'Cth: 30 hari setelah barang diterima'],
                ],
                'body_html' => <<<'HTML'
<p>Bersama ini kami memesan barang/jasa berikut untuk keperluan <strong>{{task.title}}</strong>:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 34%">Uraian Barang / Jasa</th>
    <th style="width: 10%">Qty</th>
    <th style="width: 10%">Satuan</th>
    <th style="width: 20%">Harga Satuan (Rp)</th>
    <th style="width: 20%">Jumlah (Rp)</th>
  </tr>
  <tr><td>1</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>3</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td colspan="5" style="text-align: right"><strong>Subtotal</strong></td><td>&nbsp;</td></tr>
  <tr><td colspan="5" style="text-align: right"><strong>PPN 11%</strong></td><td>&nbsp;</td></tr>
  <tr><td colspan="5" style="text-align: right"><strong>Total</strong></td><td>&nbsp;</td></tr>
</table>
<p><strong>Ketentuan Pesanan</strong></p>
<ol>
  <li>Pengiriman dialamatkan ke lokasi yang ditetapkan {{org.name}} dan disertai Surat Jalan.</li>
  <li>Barang yang tidak sesuai spesifikasi dapat ditolak dan menjadi tanggung jawab pemasok.</li>
  <li>Faktur/invoice diterbitkan setelah barang diterima baik dan dituangkan dalam Berita Acara Serah Terima.</li>
  <li>Pembayaran mengikuti termin pada kolom isian di atas.</li>
</ol>
<p>Pesanan ini sah tanpa memerlukan pembubuhan cap perusahaan pada salinan elektroniknya.</p>
HTML,
            ],

            [
                'code'        => 'DOR',
                'name'        => 'Delivery Order / Surat Jalan',
                'category'    => 'Pengadaan',
                'description' => 'Dokumen pengantar pengiriman barang, memuat rincian muatan, kendaraan, dan tanda terima penerima.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'penerima',    'label' => 'Dikirim Kepada', 'type' => 'text'],
                    ['key' => 'alamat',      'label' => 'Alamat Tujuan', 'type' => 'text'],
                    ['key' => 'no_polisi',   'label' => 'Nomor Polisi Kendaraan', 'type' => 'text', 'placeholder' => 'Cth: BM 1234 XY'],
                    ['key' => 'sopir',       'label' => 'Nama Sopir', 'type' => 'text'],
                    ['key' => 'ref_po',      'label' => 'Nomor PO Referensi', 'type' => 'text'],
                    ['key' => 'tgl_kirim',   'label' => 'Tanggal Pengiriman', 'type' => 'date'],
                ],
                'body_html' => <<<'HTML'
<p>Mohon diterima barang berikut sesuai rincian di bawah ini:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 46%">Uraian Barang</th>
    <th style="width: 14%">Jumlah</th>
    <th style="width: 14%">Satuan</th>
    <th style="width: 20%">Keterangan</th>
  </tr>
  <tr><td>1</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>3</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 22%"><strong>Bruto</strong></td><td style="width: 28%">&nbsp;</td>
      <td style="width: 22%"><strong>Tara</strong></td><td>&nbsp;</td></tr>
  <tr><td><strong>Netto</strong></td><td>&nbsp;</td>
      <td><strong>Segel</strong></td><td>&nbsp;</td></tr>
</table>
<p><strong>Catatan</strong></p>
<ol>
  <li>Barang diterima dalam keadaan baik, lengkap, dan sesuai jumlah tercantum.</li>
  <li>Surat jalan ini dibuat rangkap tiga: pengirim, penerima, dan arsip.</li>
  <li>Keberatan atas jumlah maupun kondisi barang disampaikan pada saat serah terima.</li>
</ol>
<p>Referensi pekerjaan: <strong>{{task.title}}</strong> &mdash; PIC {{pic.name}}.</p>
HTML,
            ],

            [
                'code'        => 'INV',
                'name'        => 'Invoice / Faktur Tagihan',
                'category'    => 'Keuangan',
                'description' => 'Tagihan resmi kepada pelanggan atas barang atau jasa yang telah diserahkan.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'kepada',      'label' => 'Ditagihkan Kepada', 'type' => 'text'],
                    ['key' => 'npwp',        'label' => 'NPWP Pelanggan', 'type' => 'text'],
                    ['key' => 'alamat',      'label' => 'Alamat Pelanggan', 'type' => 'text'],
                    ['key' => 'ref_po',      'label' => 'Nomor PO / Kontrak', 'type' => 'text'],
                    ['key' => 'jatuh_tempo', 'label' => 'Tanggal Jatuh Tempo', 'type' => 'date'],
                    ['key' => 'rekening',    'label' => 'Rekening Pembayaran', 'type' => 'text', 'placeholder' => 'Bank / No. Rekening / Atas Nama'],
                ],
                'body_html' => <<<'HTML'
<p>Berikut rincian tagihan atas pekerjaan <strong>{{task.title}}</strong> yang telah diselesaikan:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 38%">Uraian</th>
    <th style="width: 10%">Qty</th>
    <th style="width: 22%">Harga Satuan (Rp)</th>
    <th style="width: 24%">Jumlah (Rp)</th>
  </tr>
  <tr><td>1</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>3</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td colspan="4" style="text-align: right"><strong>Dasar Pengenaan Pajak (DPP)</strong></td><td>&nbsp;</td></tr>
  <tr><td colspan="4" style="text-align: right"><strong>PPN 11%</strong></td><td>&nbsp;</td></tr>
  <tr><td colspan="4" style="text-align: right"><strong>TOTAL TAGIHAN</strong></td><td>&nbsp;</td></tr>
</table>
<p><strong>Terbilang:</strong> <em>&mdash;</em></p>
<p><strong>Ketentuan Pembayaran</strong></p>
<ol>
  <li>Pembayaran ditransfer ke rekening sebagaimana tercantum pada kolom isian di atas.</li>
  <li>Pembayaran dianggap lunas setelah dana efektif diterima di rekening tersebut.</li>
  <li>Mohon mencantumkan nomor invoice ini pada berita transfer.</li>
</ol>
<p>Dokumen pendukung: Berita Acara Serah Terima dan Surat Jalan terlampir.</p>
HTML,
            ],

            [
                'code'        => 'PPB',
                'name'        => 'Permintaan Pembayaran',
                'category'    => 'Keuangan',
                'description' => 'Formulir pengajuan pembayaran internal beserta jalur persetujuannya, dilampiri dokumen pendukung.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'penerima',   'label' => 'Dibayarkan Kepada', 'type' => 'text'],
                    ['key' => 'rekening',   'label' => 'Rekening Tujuan', 'type' => 'text', 'placeholder' => 'Bank / No. Rekening / Atas Nama'],
                    ['key' => 'jumlah',     'label' => 'Jumlah Diajukan (Rp)', 'type' => 'text'],
                    ['key' => 'ref_invoice', 'label' => 'Nomor Invoice Referensi', 'type' => 'text'],
                    ['key' => 'tgl_bayar',  'label' => 'Tanggal Pembayaran Diminta', 'type' => 'date'],
                    ['key' => 'sumber_dana', 'label' => 'Sumber Dana / Pos Anggaran', 'type' => 'text'],
                ],
                'body_html' => <<<'HTML'
<p>Dengan ini diajukan permintaan pembayaran dengan rincian sebagai berikut:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 32%"><strong>Keperluan</strong></td><td>{{task.title}}</td></tr>
  <tr><td><strong>Divisi Pengaju</strong></td><td>{{division.name}}</td></tr>
  <tr><td><strong>Diajukan Oleh</strong></td><td>{{pic.name}}</td></tr>
  <tr><td><strong>Tanggal Pengajuan</strong></td><td>{{today.long}}</td></tr>
</table>
<p><strong>Rincian Pembayaran</strong></p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 54%">Uraian</th>
    <th style="width: 40%">Jumlah (Rp)</th>
  </tr>
  <tr><td>1</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td colspan="2" style="text-align: right"><strong>TOTAL</strong></td><td>&nbsp;</td></tr>
</table>
<p><strong>Terbilang:</strong> <em>&mdash;</em></p>
<p><strong>Dokumen Pendukung</strong></p>
<ol>
  <li>Invoice / faktur tagihan dari penerima pembayaran.</li>
  <li>Berita Acara Serah Terima atau bukti penyelesaian pekerjaan.</li>
  <li>Purchase Order / kontrak yang mendasari.</li>
  <li>Faktur pajak (bila ada).</li>
</ol>
<p><strong>Jalur Persetujuan</strong></p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><th style="width: 33%">Diajukan</th><th style="width: 33%">Diverifikasi</th><th>Disetujui</th></tr>
  <tr><td style="height: 62px">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>{{pic.name}}<br>Pengaju</td><td>&nbsp;<br>Finance</td><td>&nbsp;<br>Direksi</td></tr>
</table>
HTML,
            ],

            [
                'code'        => 'BST',
                'name'        => 'BAST — Berita Acara Serah Terima Pekerjaan',
                'category'    => 'Serah Terima',
                'description' => 'Serah terima hasil pekerjaan atau barang dari pelaksana kepada penerima, sebagai dasar penagihan.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'pihak_kedua',   'label' => 'Pihak Penerima', 'type' => 'text'],
                    ['key' => 'wakil_kedua',   'label' => 'Diwakili Oleh', 'type' => 'text'],
                    ['key' => 'jabatan_kedua', 'label' => 'Jabatan Penerima', 'type' => 'text'],
                    ['key' => 'ref_kontrak',   'label' => 'Nomor Kontrak / PO', 'type' => 'text'],
                    ['key' => 'lokasi',        'label' => 'Lokasi Serah Terima', 'type' => 'text'],
                ],
                'body_html' => <<<'HTML'
<p>Pada hari ini, {{today.long}}, bertempat sebagaimana tercantum pada kolom isian, telah dilaksanakan serah terima dengan rincian berikut:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 18%"><strong>PIHAK PERTAMA</strong></td>
      <td>{{pic.name}} &mdash; {{division.name}}, bertindak untuk dan atas nama {{org.name}}, selanjutnya disebut <strong>PIHAK PERTAMA</strong> (yang menyerahkan).</td></tr>
  <tr><td><strong>PIHAK KEDUA</strong></td>
      <td>Sebagaimana tercantum pada kolom isian di atas, selanjutnya disebut <strong>PIHAK KEDUA</strong> (yang menerima).</td></tr>
</table>
<p><strong>Pasal 1 — Objek Serah Terima</strong></p>
<p>PIHAK PERTAMA menyerahkan dan PIHAK KEDUA menerima hasil pekerjaan <strong>{{task.title}}</strong> dengan rincian:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr>
    <th style="width: 6%">No</th>
    <th style="width: 48%">Uraian Pekerjaan / Barang</th>
    <th style="width: 14%">Volume</th>
    <th style="width: 32%">Keterangan</th>
  </tr>
  <tr><td>1</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>2</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>3</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
<p><strong>Pasal 2 — Pernyataan Penerimaan</strong></p>
<p>PIHAK KEDUA menyatakan telah memeriksa dan menerima hasil pekerjaan tersebut dalam keadaan baik, lengkap, serta sesuai dengan spesifikasi yang diperjanjikan.</p>
<p><strong>Pasal 3 — Akibat Hukum</strong></p>
<p>Dengan ditandatanganinya berita acara ini, pekerjaan dinyatakan <strong>SELESAI 100%</strong> dan menjadi dasar sah bagi PIHAK PERTAMA untuk mengajukan penagihan sesuai ketentuan kontrak.</p>
<p>Berita acara ini dibuat rangkap dua, masing-masing mempunyai kekuatan hukum yang sama.</p>
HTML,
            ],

            [
                'code'        => 'SKL',
                'name'        => 'Surat Keluar Resmi',
                'category'    => 'Surat',
                'description' => 'Surat resmi perusahaan kepada pihak eksternal — pemberitahuan, permohonan, undangan, atau klarifikasi.',
                'orientation' => 'portrait',
                'fields'      => [
                    ['key' => 'kepada',   'label' => 'Kepada Yth.', 'type' => 'text'],
                    ['key' => 'jabatan',  'label' => 'Jabatan Penerima', 'type' => 'text'],
                    ['key' => 'instansi', 'label' => 'Instansi / Perusahaan', 'type' => 'text'],
                    ['key' => 'alamat',   'label' => 'Alamat', 'type' => 'text'],
                    ['key' => 'perihal',  'label' => 'Perihal', 'type' => 'text'],
                    ['key' => 'lampiran', 'label' => 'Lampiran', 'type' => 'text', 'placeholder' => 'Cth: 1 (satu) berkas'],
                ],
                'body_html' => <<<'HTML'
<p>Dengan hormat,</p>
<p>Sehubungan dengan <strong>{{task.title}}</strong>, bersama ini kami sampaikan hal-hal sebagai berikut:</p>
<p>{{task.description}}</p>
<ol>
  <li>&nbsp;</li>
  <li>&nbsp;</li>
  <li>&nbsp;</li>
</ol>
<p>Demikian surat ini kami sampaikan. Atas perhatian dan kerja sama yang baik, kami ucapkan terima kasih.</p>
HTML,
            ],
        ];
    }

}
