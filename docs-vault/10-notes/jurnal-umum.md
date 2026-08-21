---
title: Jurnal Umum
aliases: [General Journal, Jurnal]
tags: [akuntansi, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[akuntansi-moc]]", "[[multi-level-approval]]", "[[format-nomor-jurnal]]", "[[subledger-vendor]]", "[[subledger-customer]]"]
---
# Jurnal Umum

Pencatatan transaksi double-entry. Menu: **Akuntansi → Jurnal Umum**. Controller: `JournalController`.

## Alur status
`draft` → (submit) → `pending approval L1` → (approve) → `posted`, atau eskalasi ke L2 jika di atas ambang — detail lengkap di [[multi-level-approval]].

## Elemen penting
- **Kode bantu (`aux_code`)**: tag opsional pada `JournalLine` untuk mengaitkan baris ke vendor/customer tertentu — dasar dari [[subledger-vendor]] dan [[subledger-customer]]. `auxCodes()` di controller menggabungkan daftar vendor + customer dengan label `(Vendor)` / `(Customer)`.
- **Lampiran**: upload bukti pendukung via `JournalAttachment`.
- **Insight/analisis**: ringkasan otomatis saat entri jurnal dibuat.
- Hanya jurnal dengan `is_posted = true` yang tampil di [[laporan-keuangan]] dan subledger.
- Model `JournalLine` memakai `$timestamps = false`.

## Nomor jurnal
Lihat [[format-nomor-jurnal]] — `JE-YYYYMM-NNNN`.
