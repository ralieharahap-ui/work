---
title: Chart of Accounts (COA)
aliases: [Daftar Akun, COA]
tags: [akuntansi, fitur]
type: note
created: 2026-08-16
updated: 2026-08-20
status: evergreen
related: ["[[akuntansi-moc]]", "[[jurnal-umum]]", "[[data-model]]"]
---
# Chart of Accounts (COA)

Master daftar akun perusahaan. Menu: **Akuntansi → Daftar Akun**.

## COA Restated (revisi 2026-08-20)
COA dibangun ulang mengikuti dokumen revisi PT GEP menjadi **58 akun kode 4-digit**:
- Format kode: `XYYY` — `1xxx` Aset · `2xxx` Liabilitas · `3xxx` Ekuitas · `4xxx` Pendapatan · `5xxx` HPP/COGS · `6xxx` Beban (OPEX & Lain).
- Kolom baru `fs_group` (**Kelompok FS**): Aset Lancar, Aset Kontra, Persediaan, Pajak, Aset Tetap, Aset Tetap Kontra, Aset Takberwujud, Liabilitas Lancar, Liabilitas Pajak, Ekuitas, Pendapatan, Pendapatan Lain, COGS, OPEX, Beban Lain. Migration `2026_08_20_000001_add_fs_group_to_accounts` (aditif).
- UI mengelompokkan akun per `fs_group` dengan subtotal & badge posisi normal (Db/Kr).
- Akun pajak eksplisit: PPN Masukan `1501`, PPN Keluaran `2201`, PPh 21/22/23/Final terutang `2202-2205`, PPh 22/23 dibayar dimuka `1502/1503` — dipakai modul [[kontrol-pajak]].
- Akun penyusutan: Beban Penyusutan `6112` & Akumulasi Penyusutan `1609` — dipakai auto-jurnal penyusutan (lihat [[aset-tetap]]).

## Perilaku seeder & migrasi legacy
- `ChartOfAccountsSeeder` idempoten (`firstOrCreate` + patch metadata kosong).
- Akun legacy format lama `X-XXXX` yang **belum dipakai jurnal** dinonaktifkan (`is_active=false`, reversible); yang sudah dipakai tetap aktif agar jurnal historis valid.

## Umum
- CRUD (store/update/destroy) dibatasi role `super_admin` — lihat [[role-permission]].
- Kolom kunci: `account_type` (control account, mis. `Piutang Usaha`, `PPN Masukan`), dipakai subledger — lihat [[subledger-vendor]], [[subledger-customer]].
- Controller: `AccountController` — `controlAccounts()` mendaftar tipe kontrol akun referensi (posisi normal Db/Kr + peta laporan NRC/LR).
