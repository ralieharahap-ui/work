---
title: Akuntansi — Map of Content
aliases: [Akuntansi MOC, Books MOC]
tags: [moc, akuntansi]
type: moc
created: 2026-08-16
updated: 2026-08-20
status: evergreen
related: ["[[Home]]"]
---
# 📒 Akuntansi — Map of Content

Inti akuntansi aplikasi (menu **Akuntansi** & **Laporan**). Berbasis double-entry, jurnal yang di-*post* menjadi sumber semua laporan & subledger.

## Master & pencatatan
- [[chart-of-accounts]] — daftar akun (COA restated, 58 akun kode 4-digit + Kelompok FS)
- [[jurnal-umum]] — pencatatan jurnal umum (draft → posted), dengan saran akun otomatis
- [[multi-level-approval]] — alur persetujuan jurnal berjenjang
- [[buku-besar]] — buku besar & neraca lajur per akun
- [[aset-tetap]] — daftar aset tetap + auto-jurnal penyusutan

## Automasi
- [[automasi-akuntansi]] — recommender kode akun, kontrol pajak, auto-penyusutan, dashboard manajemen, audit AI

## Subledger (buku pembantu)
- [[subledger-vendor]] — utang vendor, otomatis dari jurnal via kode bantu
- [[subledger-customer]] — piutang customer, pola serupa vendor

## Laporan
- [[laporan-keuangan]] — Neraca, Laba/Rugi, Neraca Saldo, Neraca Lajur, Peredaran Bruto
- Kontrol Pajak (PPN & PPh) · Audit AI (anomali jurnal) · Dashboard Akuntansi — lihat [[automasi-akuntansi]]

## Konvensi
- [[format-nomor-jurnal]] — `JE-YYYYMM-NNNN`
- Laporan & subledger hanya membaca jurnal dengan `is_posted = true`.

## MOC terkait
- [[dokumen-moc]] (dokumen tertentu meng-auto-jurnal) · [[teknis-moc]]
