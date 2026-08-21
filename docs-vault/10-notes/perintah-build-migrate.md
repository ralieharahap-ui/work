---
title: Perintah Build, Migrate & Tinker
aliases: [Build Migrate, Artisan Commands]
tags: [teknis, referensi]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[teknis-moc]]", "[[environment-setup]]"]
---
# Perintah Build, Migrate & Tinker

Semua perintah `artisan` memakai path PHP Laragon lengkap — lihat [[environment-setup]].

## Migrate & seed
```bash
"C:\Working _Space_Ralie\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan migrate --seed
```

## Build frontend
```bash
npm run build
```
(Vite dev server **opsional** — jika `public/hot` tertinggal dari sesi Vite yang sudah mati, hapus file itu agar Laravel kembali pakai manifest hasil build, bukan mencoba konek ke dev server mati.)

## Tinker multi-baris (PowerShell-safe)
PowerShell merusak tanda kutip pada `--execute`, jadi:
1. Tulis skrip `.php` ke scratchpad.
2. Jalankan: `php artisan tinker <path-ke-file.php>`

Ini pola yang dipakai berulang kali untuk verifikasi data (cleanup, setup data uji, cek saldo subledger) — lihat [[subledger-vendor]], [[subledger-customer]].
