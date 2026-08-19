---
title: Role & Permission
aliases: [Hak Akses, Role Permission, User Roles]
tags: [user-role, referensi]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[user-role-moc]]", "[[multi-level-approval]]"]
---
# Role & Permission

Menggunakan paket **Spatie Laravel-Permission**.

## Role yang ada
`super_admin`, `drafter`, `reviewer`, `approval`, `external`.

## Struktur permission
Kombinasi **modul × aksi**:
- Modul: `inventory`, `invoice`, `billing`, `books`, `letters`
- Aksi: `view`, `create`, `edit`, `delete`, `approve`

Contoh: `inventory.view`, `books.approve`.

## Pemakaian di kode
- `$user->hasRole('super_admin')`
- `$user->can('books.approve')`
- `$user->hasAnyRole(['reviewer', 'approval'])`

## Terkait
Role `reviewer`/`approval`/`super_admin` menentukan level pada [[multi-level-approval]]. Menu **Manajemen User** (CRUD user & role) hanya untuk `super_admin`, di luar grup manapun di sidebar.
