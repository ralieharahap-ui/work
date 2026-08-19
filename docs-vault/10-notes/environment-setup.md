---
title: Environment Setup (Laragon/MySQL)
aliases: [Setup Environment, Laragon Path]
tags: [teknis, referensi]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[teknis-moc]]", "[[stack-teknologi]]", "[[perintah-build-migrate]]"]
---
# Environment Setup (Laragon/MySQL)

- **PHP**: via Laragon, folder custom, **tidak ada di PATH**:
  `C:\Working _Space_Ralie\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
  Ditemukan lewat `Get-Process` (path `php-cgi` mengungkap folder instalasi). Path ini sudah diset di `.claude/launch.json`.
- **MySQL**: `C:\Working _Space_Ralie\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe` — **jalankan manual** sebagai proses background jika port 3306 mati (jangan asumsikan Laragon auto-start MySQL).
- **Node/npm**: sudah ada di PATH, tidak perlu path khusus.
- Selalu jalankan `artisan` lewat path PHP lengkap di atas (bukan `php artisan ...` biasa).

> Referensi silang: catatan asli tersimpan juga di memori Claude (`laragon-php-path` di folder memory), yang menaut balik ke catatan ini.
