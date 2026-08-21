---
title: Template Dokumen (Design & Generate)
aliases: [Dokumen Template, Document Templates]
tags: [dokumen, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[dokumen-moc]]", "[[format-nomor-dokumen]]", "[[tanda-tangan-dokumen]]", "[[auto-jurnal-dokumen]]", "[[tabel-kustom-dokumen]]"]
---
# Template Dokumen (Design & Generate)

Dua konsep berbeda dari spesifikasi awal:
- **Design Dokumen Template** — tampilan template statis (referensi visual).
- **Generate Dokumen Template** — form isi data → cetak/simpan → tersimpan sebagai riwayat.

Controller: `DocumentController`, Model: `Document`. Halaman: `resources/js/Pages/Documents/{Index,Create,Show,Log}.jsx`.

## 11 jenis dokumen aktif
Terdaftar di `types()` registry pada `DocumentController`, masing-masing punya prefix nomor sendiri (mis. `SJ` = Surat Jalan, `SR` = Surat Resmi). Lihat [[format-nomor-dokumen]].

Termasuk (tidak lengkap semua nama, lihat kode untuk daftar penuh):
- [[surat-jalan]] (SJ)
- [[perjalanan-dinas-reimbursement]] — Perjalanan Dinas (PD) & Reimbursement (RB)
- [[surat-resmi]] (SR) — satu-satunya template dengan opsi tabel isi **kustom**, lihat [[tabel-kustom-dokumen]]
- Kwitansi, Voucher, Tanda Terima, Delivery Order (DO), dan lainnya

## Elemen umum tiap template
- **Kop surat**: `company()` di `DocumentController` — alamat Signatur Park Grande, website `www.geosys-ep.com`, email `cs.admin@geosys-ep.com`, telp `+6287893024936` / `+628192430521`.
- **Kalimat pembuka (narasi)** dan **kolom referensi** (nomor & tanggal Kontrak/SPK/DO) — mendukung **lebih dari satu input referensi** (array, bukan field tunggal).
- **Footer**: website, nomor halaman, kode unik dokumen.
- **Status**: `statusOptions()` — siklus draft → rilis, dicatat dalam riwayat (`log`).
- Nomor + riwayat otomatis tersimpan per dokumen yang dibuat.

## Alur data (teknis)
Karena Inertia `useForm` meng-async `setData`, pengiriman jumlah/angka harus pakai `transform()` untuk menyuntikkan data final sebelum `post()` — bug awal "amounts tidak tersimpan" diperbaiki dengan pola ini.

> [!warning] Jebakan closure pada state gabungan
> Pola serupa muncul lagi di [[tabel-kustom-dokumen]]: dua panggilan `setMeta()` berurutan dalam satu fungsi event-handler sama-sama memakai `meta` lama dari closure — panggilan kedua menimpa efek panggilan pertama. Solusi: gabungkan jadi **satu** panggilan `setMeta()` yang membawa semua field yang berubah sekaligus.
