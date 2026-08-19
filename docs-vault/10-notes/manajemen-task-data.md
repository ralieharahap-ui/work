---
title: Modul Manajemen Tugas
aliases: [Manajemen Task, Task Management, Tasks, Manajemen Tugas]
tags: [teknis, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[deployment-railway]]", "[[data-model]]", "[[role-permission]]", "[[teknis-moc]]"]
---
# Modul Manajemen Tugas

Modul task management bergaya Notion, di-port dari proyek Railway "Manajemen Task" (repo `ralieharahap-ui/work`) ke aplikasi PT GEP ERP ini. Data historisnya diimpor lebih dulu — lihat [[deployment-railway]].

Menu: **Manajemen Tugas** (`/tasks`), di luar grup collapsible (sejajar dengan Manajemen User).

## Empat tab
- **Papan Kanban** — kolom To Do / In Progress / Review / Blocked / Done, ganti status langsung dari kartu, filter kategori (Operasional/Strategis), banner Tugas Kadaluarsa & Prioritas Tinggi.
- **Dashboard** — statistik task aktif/kadaluarsa, proyek berjalan, total anggota, beban kerja per anggota, dan daftar task mendekati deadline.
- **Proyek** — daftar proyek strategis dengan owner, tenggat, dan progress checklist.
- **Tim** — direktori anggota + hak akses; super admin bisa tambah/edit pengguna langsung dari sini (memakai modal yang sama dengan halaman Manajemen User).

## Aturan penting
- **Menutup task wajib melampirkan dokumen bukti** (evidence, maks 10 MB: pdf/jpg/png/doc/docx/xls/xlsx). Status tidak bisa langsung digeser ke `Done` dari Kanban — harus lewat endpoint `close` yang memvalidasi lampiran.
- Task `Done` yang dibuka kembali otomatis membersihkan `closed_by`/`closed_at` agar metadata tidak bertentangan dengan status.
- Mengedit task yang sudah `Done` tidak memaksa unggah ulang bukti — evidence hanya diwajibkan saat task *beralih* menjadi Done.

## Berkas
- Controller: `TaskController`, `TaskProjectController`, `TaskCommentController`.
- Service: `TaskAlertService` (menghitung peringatan deadline/prioritas per user).
- Model: `Task`, `TaskProject`, `TaskChecklistItem`, `TaskComment`.
- Halaman: `resources/js/Pages/Tasks/` (Index + KanbanView, DashboardView, ProjectsView, TeamView, TaskModal, ProjectModal, CloseTaskModal, TaskCard, CommentThread, AlertsBanner, constants).
- Migrasi: `2026_08_16_000001_create_task_management_tables.php`.

## Permission
Modul `tasks` ditambahkan ke `RolePermissionSeeder` (`tasks.view/create/edit/delete/approve`) mengikuti matriks role yang sudah ada — lihat [[role-permission]]. Penambahan bersifat aditif; permission modul lain tidak diubah.

## Catatan gaya visual
Modul ini memakai tema terang bergaya Notion (berbeda dari tema gelap aplikasi utama) — sesuai desain aslinya. Palet `notion.*` dan `warm.*` beserta shadow `notion`/`notion-deep` **ditambahkan** ke `tailwind.config.js` tanpa mengubah palet `brand`/`blue`/`slate` yang sudah dipakai halaman lain.
