---
title: Menu Grup Supply Chain
aliases: [Supply Chain Menu, Grup Menu Supply Chain]
tags: [supply-chain, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[supply-chain-moc]]", "[[role-permission]]"]
---
# Menu Grup Supply Chain

Grup menu collapsible di sidebar (`resources/js/Layouts/AppLayout.jsx`), sama pola dengan grup Akuntansi/Laporan/Dokumen.

## Item dalam grup
- Dashboard (peta) — `href: '/'`
- Sumber Cangkang — `/palm-oil-sources`
- Titik Bongkar — `/unloading-points`
- Titik Dermaga — `/jetty-points`
- Kalkulasi Proyek — `/project-calculator`

## Implementasi
- Tiap item nav diberi properti `group: 'Supply Chain'`.
- State `openGroups` (lazy init: grup berisi halaman aktif otomatis terbuka), fungsi `toggleGroup`, `groupHasActive`, ikon `ChevronDownIcon` untuk indikator buka/tutup.
- Menu **Manajemen User** tetap **di luar grup** (ungrouped, khusus `super_admin`).
- Perilaku terverifikasi: klik judul grup → collapse (sembunyikan 5 item) / expand (tampilkan kembali), identik dengan grup Akuntansi/Laporan/Dokumen.
