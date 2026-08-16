---
title: Aset Tetap (Fixed Assets)
aliases: [Fixed Assets, Daftar Aset]
tags: [akuntansi, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[akuntansi-moc]]", "[[data-model]]"]
---
# Aset Tetap (Fixed Assets)

Menu: **Akuntansi → Daftar Aset**. Controller: `FixedAssetController`, Model: `FixedAsset`. Halaman: `resources/js/Pages/Books/FixedAssets.jsx`.

- CRUD termasuk `update()` dengan flag `can_manage` untuk kontrol siapa boleh mengubah data aset.
- Migrasi: `2026_08_15_000003_create_fixed_assets_table.php`.
