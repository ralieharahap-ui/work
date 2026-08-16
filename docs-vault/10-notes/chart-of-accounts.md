---
title: Chart of Accounts (COA)
aliases: [Daftar Akun, COA]
tags: [akuntansi, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[akuntansi-moc]]", "[[jurnal-umum]]", "[[data-model]]"]
---
# Chart of Accounts (COA)

Master daftar akun perusahaan. Menu: **Akuntansi → Daftar Akun**.

- Format kode akun: `X-XXXX` (digit pertama = tipe akun: 1=Aset, 2=Liabilitas, 3=Ekuitas, 4=Pendapatan, 5=Beban dst.).
- CRUD (store/update/destroy) dibatasi role `super_admin` — lihat [[role-permission]].
- Kolom kunci: `account_type` (mis. `Piutang%`, `liability`), `control_account` (dipakai subledger — lihat [[subledger-vendor]], [[subledger-customer]]).
- Seed dasar: 18 akun via `ChartOfAccountsSeeder`, termasuk `2-2400 Utang Pihak Berelasi` (liability, kontrol `Utang Usaha`) yang ditambahkan untuk kebutuhan [[auto-jurnal-dokumen]].
- Controller: `AccountController` — `controlAccounts()` mendaftar 21 tipe kontrol akun yang tersedia.
