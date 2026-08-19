---
title: Akuntansi — Map of Content
aliases: [Akuntansi MOC, Books MOC]
tags: [moc, akuntansi]
type: moc
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[Home]]"]
---
# 📒 Akuntansi — Map of Content

Inti akuntansi aplikasi (menu **Akuntansi** & **Laporan**). Berbasis double-entry, jurnal yang di-*post* menjadi sumber semua laporan & subledger.

## Master & pencatatan
- [[chart-of-accounts]] — daftar akun (COA), format kode `X-XXXX`
- [[jurnal-umum]] — pencatatan jurnal umum (draft → posted)
- [[multi-level-approval]] — alur persetujuan jurnal berjenjang
- [[buku-besar]] — buku besar & neraca lajur per akun
- [[aset-tetap]] — daftar aset tetap (fixed assets)

## Subledger (buku pembantu)
- [[subledger-vendor]] — utang vendor, otomatis dari jurnal via kode bantu
- [[subledger-customer]] — piutang customer, pola serupa vendor

## Laporan
- [[laporan-keuangan]] — Neraca, Laba/Rugi, Neraca Saldo, Neraca Lajur, Peredaran Bruto

## Konvensi
- [[format-nomor-jurnal]] — `JE-YYYYMM-NNNN`
- Laporan & subledger hanya membaca jurnal dengan `is_posted = true`.

## MOC terkait
- [[dokumen-moc]] (dokumen tertentu meng-auto-jurnal) · [[teknis-moc]]
