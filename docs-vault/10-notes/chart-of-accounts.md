---
title: Chart of Accounts (COA)
aliases: [Daftar Akun, COA, COA PSAK 2026]
tags: [akuntansi, fitur, referensi]
type: note
created: 2026-08-16
updated: 2026-10-08
status: evergreen
related: ["[[akuntansi-moc]]", "[[jurnal-umum]]", "[[laporan-keuangan]]", "[[data-model]]"]
---
# Chart of Accounts (COA)

Master daftar akun. Menu: **Akuntansi → Daftar Akun**. Sejak 2026-10-08 memakai **COA revisi PSAK 2026** (sumber: `PT_GEP_COA_REVISI_PSAK_2026.md` + `PT_GEP_AUDIT_COA_PSAK_2026.md`).

## Struktur
- Kode **5 digit** (desain internal PT GEP — PSAK tidak menetapkan kode baku): 1=Aset, 2=Liabilitas, 3=Ekuitas, 4=Pendapatan, 5=HPP/Beban Pokok Pendapatan, 6=Beban Operasional, 7=Pendapatan Lainnya, 8=Beban Keuangan & Lainnya, 9=Pajak Penghasilan.
- 168 akun aktif: 13 **header** (`is_header`, tidak bisa diposting) + 155 akun detail. Data sumber: `database/data/coa_psak_2026.php`.
- Kolom: `account_type` = kelompok akun, `fs_group` = sub laporan (menentukan bagian Neraca/Laba Rugi), `normal_balance` Db/Kr, `report` NRC/LR, `legacy_code` = kode lama (4 digit) untuk akun hasil pemetaan.
- Tambahan di luar dokumen revisi: **11507 PPN Dipungut Pemungut (WAPU) - Clearing** untuk fitur Kontrol PPN WAPU.

## Migrasi (2026_10_08_000002_revise_coa_psak_2026)
- Akun lama dipetakan **di tempat** (id tetap) → jurnal, aset tetap & dokumen tetap terhubung. Contoh: 1102→11109, 1202→11204, 1501→11501, 2201→21401, 2401→21303, 4102→41106, 5201→51101, 6106→62001, 6112→61901, 1609→12203.
- Akun aktif tanpa padanan & tanpa jurnal dinonaktifkan (1203, 5207–5209, 5299) — tidak dihapus.
- Cadangan tabel lama: `accounts_backup_coa_2026` (lokal saja, untuk rollback `migrate:rollback`).
- Verifikasi: Neraca Saldo, laba bersih & Neraca identik sebelum/sesudah migrasi.

## Aturan aplikasi terkait
- Jurnal, dokumen PD/RB & aset tetap hanya menampilkan/menerima akun detail aktif (`Account::postable()`); server menolak posting ke header.
- Kontrol PPN: 21401 PPN Keluaran, 11501 PPN Masukan, 11507 WAPU.
- Kreditur Pendanaan: kelompok *Pinjaman Jangka Pendek / Bagian Lancar*, *Pinjaman Jangka Panjang*, *Hutang Pihak Berelasi*.
- Subledger customer: kelompok `Piutang%`; vendor: akun liabilitas — lihat [[subledger-vendor]], [[subledger-customer]].
- Default auto-jurnal Reimbursement/PD: Debit beban (61502/62003), Kredit 21303 Hutang kepada Direksi — lihat [[auto-jurnal-dokumen]].
- Penyajian laporan: lihat [[laporan-keuangan]].
