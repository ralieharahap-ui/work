---
title: Subledger Utang Vendor
aliases: [Vendor Ledger, Subledger Vendor, Utang Vendor]
tags: [akuntansi, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[akuntansi-moc]]", "[[jurnal-umum]]", "[[subledger-customer]]", "[[data-model]]"]
---
# Subledger Utang Vendor

Buku pembantu utang per vendor, dihitung **on-the-fly** (tidak disimpan) dari jurnal posted. Menu: **Akuntansi → Master Vendor**. Controller: `VendorController`, Model: `Vendor`.

## Cara hitung
```
current_balance = opening_balance + movement
movement = SUM(credit - debit)   -- pada baris jurnal ber-akun liability
                                   -- yang di-tag aux_code = kode vendor
```
- `index()` menghitung `movementsByCode` dari akun bertipe `liability`, dikelompokkan per `aux_code`.
- `show()` = tampilan riwayat mutasi lengkap (ledger) per vendor, halaman `resources/js/Pages/Books/VendorLedger.jsx`.
- Kode bantu (`aux_code`) diisi di jurnal — lihat [[jurnal-umum]].

## Verifikasi
Diuji: vendor `V-001` menghasilkan saldo `6.000.000` sesuai simulasi jurnal.

## Pola serupa
Lihat [[subledger-customer]] — logika sama, dibalik untuk akun piutang.
