---
title: Home — PT GEP ERP
aliases: [Home, Index, Beranda, PT GEP]
tags: [moc]
type: moc
created: 2026-08-16
updated: 2026-08-16
status: evergreen
---
# 🏠 Home — Aplikasi ERP PT Geosys Energi Prima

Vault pengetahuan untuk aplikasi **akuntansi/ERP PT Geosys Energi Prima (PT GEP)** — pemasok cangkang sawit (palm-oil shell). Titik masuk tunggal: mulai membaca dari sini.

> [!important] Prinsip pengembangan (WAJIB)
> **Jangan ubah/hapus/ganti fitur yang sudah ada.** Semua pekerjaan bersifat *menambah* dan *mengupdate* (bukan mengganti atau menghapus). Jika ada yang kurang jelas dari sebuah prompt, **ajukan pertanyaan dulu** sebelum eksekusi.

## Peta Modul (MOC)
- [[akuntansi-moc]] — inti akuntansi: akun, jurnal, buku besar, aset, subledger, laporan
- [[dokumen-moc]] — desain & generate template dokumen resmi (11 jenis)
- [[supply-chain-moc]] — dashboard peta, sumber cangkang, titik bongkar/dermaga, kalkulasi proyek
- [[user-role-moc]] — role, hak akses, dan alur multi-level approval
- [[teknis-moc]] — stack, environment (Laragon/MySQL), build/migrate, model data

## Dashboard
- [[Dashboard]] — daftar catatan terbaru, per tag, dan status ringkas vault (Dataview)

## Akses cepat
- Login dev: akun super admin `admin@pt-gep.com` (kata sandi **tidak dicatat di sini** — repo ini publik; simpan di password manager)
- App lokal: `http://localhost:8000`
- Lihat cara jalankan: [[perintah-build-migrate]] · [[environment-setup]]

## Konvensi vault
- Frontmatter konsisten (`title, aliases, tags, type, created, updated, status, related`).
- Navigasi lewat MOC + wikilink `[[...]]`, bukan folder.
- Tag terkontrol: `akuntansi`, `dokumen`, `supply-chain`, `user-role`, `teknis`, `fitur`, `alur`, `referensi`, `moc`.
- Vault ini disimpan di `docs-vault/` (dalam repo proyek) — **bukan** di folder memory Claude, karena folder memory menulis-ulang frontmatter ke skema bersarang yang tidak kompatibel dengan Properties/Dataview Obsidian.
