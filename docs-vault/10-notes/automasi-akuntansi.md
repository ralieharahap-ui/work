---
title: Automasi Akuntansi
aliases: [Automasi, AI Akuntansi, Recommender, Audit AI, Kontrol Pajak]
tags: [akuntansi, automasi, fitur]
type: note
created: 2026-08-20
updated: 2026-08-20
status: evergreen
related: ["[[akuntansi-moc]]", "[[chart-of-accounts]]", "[[jurnal-umum]]", "[[aset-tetap]]", "[[laporan-keuangan]]"]
---
# Automasi Akuntansi

Rangkaian fitur automasi mengikuti roadmap 8 tahap dokumen revisi PT GEP.
Semua berbasis aturan deterministik (tanpa layanan AI eksternal) dan hanya
membaca jurnal `is_posted = true`.

## Tahap 2 — Rekomendasi Kode Akun (Journal coding)
- Service `App\Services\AccountRecommender` — peta kata kunci → kode akun COA restated (sisi normal Db/Kr).
- Di **Jurnal Umum**: saat mengetik Keterangan/Memo, muncul chip **💡 Saran akun** (5 teratas). Klik = isi baris jurnal otomatis.
- Validasi bawaan jurnal tetap berlaku: debet = kredit, akun aktif saja.

## Tahap 4 — Kontrol Pajak
- Menu **Laporan → Kontrol Pajak** (`ReportController::taxControl`, halaman `Books/TaxControl`).
- Rekonsiliasi PPN Masukan `1501` vs Keluaran `2201` per bulan → **kurang/(lebih) bayar**.
- PPh terutang `2202-2205` & PPh dibayar dimuka `1502/1503` per akun per bulan.

## Tahap 5-6 — Auto-Jurnal Penyusutan & Dashboard
- **Auto-jurnal penyusutan** (`FixedAssetController::postDepreciation`): di **Daftar Aset**, pilih periode → posting satu jurnal seimbang **D `6112` | K `1609`** sebesar total penyusutan aset yang masih berjalan. Guard `ref_type='depreciation'` + `ref_id=YYYY-MM` mencegah posting ganda. Khusus `super_admin`.
- **Dashboard Manajemen** (`ReportController::dashboard`, menu **Akuntansi → Dashboard Akuntansi**): ringkasan P&L YTD (pendapatan, HPP, laba kotor + margin %, OPEX, laba bersih), posisi ringkas (kas/piutang/utang), dan **margin per segmen** (Trading/Biomassa/Lumpsum vs biaya langsung).

## Tahap 8 — Layer Audit AI
- Service `App\Services\AnomalyDetector`, menu **Laporan → Audit AI** (`AuditController`, halaman `Books/Audit`).
- Antrean pengecualian: jurnal tidak seimbang (**tinggi**), akun nonaktif & kemungkinan duplikat tanggal+nilai (**sedang**), nominal nol & tanpa dokumen pendukung (**rendah**), plus **margin negatif** per bulan (pendapatan `4xxx` < HPP `5xxx`).

## Catatan
- Tahap 1 (OCR capture), 3 (auto AR/AP subledger penuh), 7 (document governance lanjutan) belum otomatis penuh — subledger vendor/customer sudah ada via kode bantu (lihat [[subledger-vendor]], [[subledger-customer]]) dan lampiran jurnal mendukung kelengkapan dokumen.
