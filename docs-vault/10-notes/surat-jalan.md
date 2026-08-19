---
title: Surat Jalan (SJ)
aliases: [Surat Jalan, SJ]
tags: [dokumen, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[template-dokumen]]", "[[tanda-tangan-dokumen]]", "[[format-nomor-dokumen]]"]
---
# Surat Jalan (SJ)

Salah satu dari 11 [[template-dokumen]]. Prefix nomor: `SJ`.

## Fitur khusus
- **Kendaraan multi-input**: bisa lebih dari 1 kendaraan, mengikuti pola input dinamis seperti kolom "Rincian Barang".
- **3 kolom tanda tangan**: Perwakilan Driver, Penerima (PLTU ...), PT Geosys Energi Prima (K3) — lihat [[tanda-tangan-dokumen]].
- **Kalimat pengantar** di awal surat.
- **Referensi wajib** ke dokumen sumber, format:
  ```
  Merujuk:
  1. Perjanjian No. ... tanggal ...
  2. Delivery Order (DO) No. ... tanggal ...
  ```
  Surat Jalan **harus** merujuk ke nomor & tanggal Kontrak/SPK **dan** nomor & tanggal DO — mendukung banyak baris referensi (bukan field tunggal).
