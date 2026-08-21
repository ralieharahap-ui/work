---
title: Auto-Jurnal saat Rilis Dokumen
aliases: [Auto Jurnal, Document Auto Journal]
tags: [dokumen, akuntansi, alur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[template-dokumen]]", "[[perjalanan-dinas-reimbursement]]", "[[jurnal-umum]]", "[[chart-of-accounts]]"]
---
# Auto-Jurnal saat Rilis Dokumen

Saat dokumen **Perjalanan Dinas (PD)** atau **Reimbursement (RB)** dirilis (status → released), sistem otomatis membuat jurnal **posted**.

## Mekanisme
- Fungsi: `postDocumentJournal()` di `DocumentController`.
- Akun debit/kredit **dipilih manual di form** oleh pembuat dokumen (bukan hardcode) — disimpan di `meta.extra.debit_account_id` / `meta.extra.credit_account_id`.
- ID jurnal hasil auto-post disimpan balik ke `meta.extra.posted_journal_id` untuk **mencegah duplikasi** jurnal jika dokumen diproses ulang.

## Contoh kasus nyata
Reimbursement atas nama Anton sebesar Rp5.000.000 → **Debit Beban** / **Kredit Utang Pihak Berelasi** (akun `2-2400`, ditambahkan khusus untuk kebutuhan ini — lihat [[chart-of-accounts]]).

## Item biaya
PD dan Reimbursement mendukung **rincian item biaya lebih dari satu (>1)** dalam satu dokumen (tabel item), dijumlahkan sebagai total jurnal.
