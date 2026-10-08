---
title: Laporan Keuangan
aliases: [Laporan, Financial Reports, Neraca, Laba Rugi]
tags: [akuntansi, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[akuntansi-moc]]", "[[jurnal-umum]]", "[[buku-besar]]"]
---
# Laporan Keuangan

Grup menu **Laporan**. Controller: `ReportController`. Halaman: `resources/js/Pages/Books/{Worksheet,BalanceSheet,ProfitLoss,GrossTurnover}.jsx` + Trial Balance.

## Jenis laporan
- **Neraca Lajur** (Worksheet) — kertas kerja penyesuaian.
- **Neraca** (Balance Sheet) — posisi keuangan.
- **Laba/Rugi** (Profit & Loss) — kinerja periode.
- **Neraca Saldo** (Trial Balance) — saldo tiap akun.
- **Peredaran Bruto** (Gross Turnover) — omzet kotor.

## Aturan dasar
Semua laporan **hanya membaca jurnal dengan `is_posted = true`** (jurnal draft/pending tidak ikut dihitung) — lihat [[jurnal-umum]].

## Penyajian PSAK 201 (sejak COA revisi 2026-10-08)
Bagian ditentukan dari `fs_group` akun (sub laporan COA), cadangan dari digit awal kode — lihat [[chart-of-accounts]].
- **Laba Rugi bertahap**: Pendapatan (4) − Beban Pokok Pendapatan (5) = **Laba Bruto**; − Beban Operasional (6) = **Laba Usaha**; + Pendapatan Lainnya (7) − Beban Keuangan & Lainnya (8) = **Laba Sebelum Pajak**; − Pajak Penghasilan (9) = **Laba Bersih**.
- **Neraca**: Aset Lancar / Tidak Lancar, Liabilitas Jangka Pendek / Panjang, Ekuitas. Laba yang belum ditutup dipisah: *Saldo Laba tahun-tahun lalu* vs *Laba (Rugi) Tahun Berjalan* (dulu dicampur sejak awal pencatatan). Akun kontra (akumulasi penyusutan, CKPN, dividen, retur penjualan) otomatis mengurangi.
- **Peredaran Bruto** & KPI penjualan dashboard: hanya pendapatan usaha (4xxxx); pendapatan lainnya (bunga, laba penjualan aset) tidak lagi ikut dihitung.
- **Neraca Saldo**: hanya akun detail (header tidak ikut).
