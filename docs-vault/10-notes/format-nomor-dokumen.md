---
title: Format Nomor Dokumen
aliases: [Nomor Surat, Document Number Format]
tags: [dokumen, referensi]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[template-dokumen]]", "[[format-nomor-jurnal]]"]
---
# Format Nomor Dokumen

Format: **`[nomor urut]/GEP/[singkatan dokumen]/[bulan berjalan dalam romawi]/[tahun berjalan]`**

Contoh: `003/GEP/SJ/VIII/2026` (Surat Jalan urutan ke-3, Agustus, 2026).

- Nomor urut **reset setiap tahun, per jenis dokumen** (SJ punya urutan sendiri, SR punya urutan sendiri, dst).
- Implementasi: `generateNumber()` + `romanMonth()` di `DocumentController`.
- Prefix per jenis didaftarkan di `types()` registry (mis. `SJ`, `SR`, `PD`, `RB`, dst).
