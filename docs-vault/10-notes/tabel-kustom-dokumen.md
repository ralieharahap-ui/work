---
title: Tabel Isi Kustom (Surat Resmi)
aliases: [Custom Table, Tabel Kustom, Editor Kolom Tabel]
tags: [dokumen, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[surat-resmi]]", "[[template-dokumen]]"]
---
# Tabel Isi Kustom (Surat Resmi)

Mode "Tabel" pada bagian **Isi Surat (Body)** di [[surat-resmi]] mendukung kolom & baris yang sepenuhnya dapat disesuaikan pengguna — bukan kolom tetap.

## Kemampuan
- **Tambah kolom** (`+ Kolom`) — kolom baru muncul dengan header "Kolom Baru", langsung dapat diedit.
- **Ubah nama header** — input teks langsung di sel header tabel (bukan dialog terpisah).
- **Hapus kolom** — tombol ✕ di header; minimal 1 kolom harus tersisa.
- **Tambah/kurang baris** — tombol `+ Baris` dan ✕ per baris (pola sama seperti tabel item dokumen lain).
- Kolom default saat dokumen baru dibuat: **Uraian, Qty, Satuan, Keterangan** (align qty ke kanan) — identik dengan tampilan lama, jadi tidak mengubah pengalaman default.

## Implementasi (frontend)
- File: `resources/js/Pages/Documents/Create.jsx` (editor) & `Show.jsx` (cetak/tampilan).
- Struktur data: `meta.extra.table_columns` = array `{ key, label, align }`; `meta.items` = array baris, tiap baris adalah object dengan key dinamis sesuai `table_columns` saat ini.
- `DEFAULT_TABLE_COLUMNS` didefinisikan di kedua file sebagai fallback — dokumen **lama** yang tersimpan sebelum fitur ini ada (tanpa `table_columns` di meta) otomatis memakai 4 kolom default saat ditampilkan, sehingga **tidak ada data lama yang rusak/hilang**.
- Fungsi kunci di `Create.jsx`: `addTableColumn`, `removeTableColumn`, `setTableColumnLabel`, `addTableRow`, `removeTableRow` (`setItem`/`removeItem` yang sudah ada dipakai ulang untuk sel & baris karena sifatnya generik terhadap key).

## Bug yang ditemukan & diperbaiki saat implementasi
`addTableColumn`/`removeTableColumn` awalnya memanggil `setExtra(...)` lalu `setMeta({items: ...})` sebagai **dua panggilan terpisah** dalam fungsi yang sama. Keduanya membaca `meta` yang sama dari closure (state React belum ter-update di antara dua panggilan sinkron), sehingga panggilan kedua menimpa balik perubahan `extra.table_columns` dari panggilan pertama — kolom baru tidak pernah muncul. Diperbaiki dengan menggabungkan jadi **satu** `setMeta({ extra: {...}, items: [...] })`.

## Verifikasi
Diuji langsung di browser (halaman Buat Surat Resmi): tambah kolom → berhasil muncul kolom baru; ubah nama header → tersimpan di state; hapus kolom → jumlah kolom berkurang sesuai; tambah/hapus baris → jumlah baris & sel per baris konsisten dengan jumlah kolom aktif.
