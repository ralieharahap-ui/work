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
        foreach ($this->templates() as $template) {
            EvidenceTemplate::updateOrCreate(
                ['code' => $template['code']],
                $template + ['organization_id' => null, 'is_system' => true, 'is_active' => true],
            );
        }
    }

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
}
