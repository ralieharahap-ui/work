---
title: Manajemen User (Super Admin)
aliases: [Manajemen User, Admin Users, Kelola Pengguna]
tags: [user-role, fitur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[role-permission]]", "[[user-role-moc]]", "[[manajemen-task-data]]"]
---
# Manajemen User (Super Admin)

Menu **Manajemen User** (`/admin/users`), khusus role `super_admin`. Controller: `AdminUserController`, halaman: `resources/js/Pages/Admin/Users/Index.jsx`.

## Kemampuan
| Aksi | Route | Keterangan |
|---|---|---|
| Lihat & filter | `admin.users.index` | Filter Semua/Menunggu/Aktif + pencarian nama/email, paginasi 15 |
| **Tambah pengguna** | `admin.users.store` | Buat akun langsung aktif, tentukan divisi + role + password awal |
| **Edit pengguna** | `admin.users.update` | Ubah nama, email, divisi, role; password opsional (kosongkan bila tidak direset) |
| **Hapus pengguna** | `admin.users.destroy` | Hapus akun permanen, dengan dialog konfirmasi bernada peringatan |
| Aktifkan | `admin.users.activate` | Menyetujui akun hasil signup |
| Nonaktifkan | `admin.users.deactivate` | Blokir login tanpa menghapus akun |

## Pengaman (guard) yang berlaku
- Semua aksi dibatasi ke user dalam **organisasi yang sama** (`abort_if` bila beda organisasi).
- **Tidak bisa menghapus atau menonaktifkan akun sendiri.**
- **Super Admin terakhir dilindungi**: tidak bisa dihapus, dan role-nya tidak bisa diturunkan — mencegah organisasi kehilangan seluruh akses administratif.

## Komponen bersama
Form tambah/edit memakai `resources/js/Components/UserFormModal.jsx` yang dipakai bersama oleh halaman ini (varian `theme="dark"`, senada tema aplikasi utama) dan tab **Tim** di [[manajemen-task-data]] (varian `theme="notion"`). Satu sumber kebenaran untuk CRUD user — keduanya memanggil route `admin.users.*` yang sama.
