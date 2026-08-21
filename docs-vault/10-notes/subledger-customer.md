---
title: Subledger Piutang Customer
aliases: [Customer Ledger, Subledger Customer, Piutang Customer]
tags: [akuntansi, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[akuntansi-moc]]", "[[jurnal-umum]]", "[[subledger-vendor]]", "[[supply-chain-moc]]", "[[data-model]]"]
---
# Subledger Piutang Customer

Buku pembantu piutang per customer, pola sama seperti [[subledger-vendor]] tapi dibalik. Menu: **Akuntansi → Master Customer**. Controller: `CustomerController` (baru).

## Cara hitung
```
current_balance = opening_balance + movement
movement = SUM(debit - credit)   -- pada baris jurnal ber-account_type LIKE 'Piutang%'
                                    -- yang di-tag aux_code = kode customer
```
- `index()`: hitung `movementsByCode`.
- `show()`: ledger/riwayat mutasi per customer — halaman `resources/js/Pages/Books/CustomerLedger.jsx`.
- `update()`: set `code` + `receivable_balance` (saldo awal), dibatasi `super_admin`.

## Catatan penting
Entitas "Customer" **memakai ulang model `UnloadingPoint`** (Titik Bongkar) — bukan tabel customer terpisah. Kolom tambahan `code` dan `receivable_balance` ditambahkan ke `UnloadingPoint` via migrasi `2026_08_15_000010_add_aux_to_unloading_points.php`. Lihat [[supply-chain-moc]].

## Verifikasi
Diuji: customer `C-001` — penjualan kredit Rp20.000.000 (Debit Piutang / Kredit Pendapatan) dikurangi penerimaan Rp8.000.000 (Debit Kas / Kredit Piutang) → saldo piutang berjalan **Rp12.000.000**, sesuai hasil test.
