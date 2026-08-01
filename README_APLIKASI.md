# 🌴 Aplikasi Sumber Biomassa PT GEP

Aplikasi ERP untuk manajemen rantai pasok cangkang sawit (biomassa) milik **PT Geosys Energi Prima**, dengan visualisasi peta interaktif sumber → titik bongkar → PLTU.

**Stack:** Laravel 11 · Inertia.js · React 18 · Tailwind CSS · Leaflet (OpenStreetMap) · MySQL

---

## ✨ Fitur

| Modul | Keterangan |
|-------|-----------|
| **Dashboard** | Kartu statistik + peta denah + ringkasan stok per sumber |
| **Manajemen Tugas** | Task management tim ala Notion — Kanban, proyek, alert deadline, bukti penyelesaian |
| **Sumber Cangkang Sawit** | Input lokasi, volume stok, nama sumber, supplier → 🟢 hijau di peta |
| **Titik Bongkar (Customer)** | Input titik bongkar customer + PIC + kapasitas → 🔴 merah di peta |
| **PLTU** | Data pembangkit tujuan → 🟠 amber di peta |

Setiap input **otomatis tervisualisasi** di peta dashboard.

---

## ✅ Modul Manajemen Tugas (`/tasks`)

Workspace task management tim bergaya **Notion** (netral hangat, border tipis, Notion Blue).

| Fitur | Implementasi |
|-------|--------------|
| **Terintegrasi antar user** | Semua task, proyek, dan komentar tersimpan per-organisasi di database dan langsung terlihat oleh seluruh anggota tim |
| **Super admin kelola akses** | Super Admin dapat **buat / edit / hapus** akun & role pengguna lain — dari tab *Tim* maupun halaman *Manajemen User*. Super Admin terakhir dilindungi agar tidak bisa dihapus/diturunkan |
| **Interaksi antar user** | Setiap task punya thread diskusi; semua anggota yang punya akses dapat berkomentar |
| **Alert deadline & prioritas** | Banner peringatan 3 kelompok: **Kadaluarsa**, **Mendekati Tenggat** (≤3 hari), dan **Prioritas Tinggi/Urgent** — plus badge merah di sidebar |
| **Wajib bukti saat closing** | Status `Done` hanya bisa dicapai lewat modal *Selesaikan Task* yang **mewajibkan unggah dokumen evidence** (PDF/gambar/dokumen, maks 10MB). Divalidasi di server, bukan hanya di UI |
| **Akses semua perangkat** | Login email + password, layout responsif (drawer di HP/tablet, sidebar di desktop) |

**Tab yang tersedia:** Papan Kanban · Dashboard · Proyek · Tim

**Peran (role) & hak akses:**

| Role | Hak |
|------|-----|
| `super_admin` | Akses penuh + kelola pengguna |
| `approval` | Kelola task & proyek, menyetujui |
| `reviewer` | Kelola & edit task, kelola proyek |
| `drafter` | Buat & kelola task |
| `external` | Hanya melihat (read-only) |

> Menutup task menyimpan file bukti ke `storage/app/public/task-evidence/`.
> Jalankan `php artisan storage:link` sekali agar file dapat diakses dari browser.

---

## 🔑 Login Demo

```
Email    : admin@pt-gep.com
Password : Admin@12345
```

---

## 🚀 Cara Menjalankan (Windows + Laragon)

Aset frontend sudah di-build (`public/build`) dan dependency PHP sudah ada (`vendor`),
jadi cukup nyalakan database + server PHP.

### 1. Nyalakan MySQL
Buka **Laragon** → **Start All**.

### 2. Buat & isi database (hanya sekali)
```bash
"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS gep_erp"
```
```bash
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan migrate --force
```
```bash
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan db:seed --force
```

### 3. Jalankan server
```bash
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan serve --host=127.0.0.1 --port=8000
```

Buka **http://localhost:8000**

> Sesuaikan versi folder PHP/MySQL bila berbeda di Laragon Anda.

---

## 🔧 Bila ingin mengubah tampilan (rebuild frontend)

Perlu Node.js:
```bash
npm install
npm run build     # atau: npm run dev (mode pengembangan)
```

---

## 📁 Struktur Penting

```
app/
  Http/Controllers/   DashboardController, PalmOilSourceController, UnloadingPointController, AuthController,
                      TaskController, TaskProjectController, TaskCommentController, AdminUserController
  Models/             PalmOilSource, UnloadingPoint, PawmPLTU, User, Organization, Division,
                      Task, TaskProject, TaskChecklistItem, TaskComment ...
  Services/           TaskAlertService (hitung alert deadline/prioritas/kadaluarsa)
database/
  migrations/         skema tabel (organizations, users, palm_oil_sources, unloading_points, tasks, ...)
  seeders/            data contoh (admin, sumber, titik bongkar, PLTU, task demo)
resources/js/
  Components/MapViewer.jsx        komponen peta Leaflet (3 jenis marker)
  Components/UserFormModal.jsx    modal buat/edit pengguna (dipakai Tasks & Admin)
  Pages/Dashboard/                dashboard + peta
  Pages/Tasks/                    modul manajemen tugas (Kanban, Dashboard, Proyek, Tim, modal)
  Pages/PalmOilSources/           CRUD sumber cangkang
  Pages/UnloadingPoints/          CRUD titik bongkar (customer)
routes/web.php        seluruh route
```

---

## ⚙️ Catatan Konfigurasi

- Database dev: MySQL `gep_erp`, user `root` tanpa password (default Laragon), lihat `.env`.
- Session & cache: driver `file` (tanpa Redis).
- Folder tidak boleh mengandung karakter `#` (kendala Vite).

---

**Versi:** 2.0 · Modul Sumber Cangkang + Titik Bongkar + Peta
**Dibuat untuk:** PT Geosys Energi Prima
