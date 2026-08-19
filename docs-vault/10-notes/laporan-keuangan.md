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
