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
| **Wajib bukti saat closing** | Status `Done` hanya bisa dicapai lewat modal *Selesaikan Task* yang **mewajibkan dokumen evidence** — berupa berkas unggahan (PDF/gambar/dokumen, maks 10MB) **atau** PDF dokumen bukti yang dibuat & ditandatangani di dalam aplikasi. Divalidasi di server, bukan hanya di UI |
| **Pengingat WhatsApp** | Pengingat otomatis harian ke PIC untuk task yang mendekati tenggat atau sudah kadaluarsa, dengan **mention nama PIC** di dalam chat |
| **Dokumen bukti & kertas kerja** | Template berita acara / kertas kerja yang bisa **disunting, dicetak, dan ditandatangani PIC** — otomatis dibekukan menjadi PDF dan siap dilampirkan sebagai syarat penutupan task |
| **Akses semua perangkat** | Login email + password, layout responsif (drawer di HP/tablet, sidebar di desktop) |

**Tab yang tersedia:** Papan Kanban · Dashboard · Proyek · Dokumen Bukti · Pengingat WA · Tim

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

## 🔔 Pengingat Tugas via WhatsApp (`tab Pengingat WA`)

Setiap hari pada jam yang ditentukan, aplikasi mengirim satu pesan ringkas ke tiap PIC
yang punya tugas mendesak. Isi pesannya membuka dengan **mention nama PIC** lalu merinci
tugas per kategori: *terlambat*, *jatuh tempo hari ini*, dan *mendekati tenggat*.

| Hal | Keterangan |
|-----|-----------|
| **Kapan dikirim** | Harian pada `WHATSAPP_REMINDER_TIME` (bawaan 08:00). Dipicu bila ada tugas dengan sisa hari sesuai `WHATSAPP_REMINDER_DAYS` (bawaan H-3, H-1, hari-H) atau keterlambatan kelipatan `WHATSAPP_OVERDUE_EVERY_DAYS` |
| **Mention** | Chat pribadi ditulis `@Nama PIC`. Bila salinan grup diaktifkan, nomor PIC ditandai sungguhan sehingga notifikasinya masuk ke ponsel yang bersangkutan |
| **Anti-spam** | Satu digest per orang per hari (dicatat lewat kunci dedupe). Tombol pengingat per task dibatasi 2 kali per 10 menit |
| **Gateway** | `go_whatsapp` atau `waha` (swakelola, **disarankan**) · `log` (uji coba) · `fonnte` · `wablas` · `cloud_api` (Meta) · `webhook` (bot sendiri) |
| **Riwayat** | Semua pengiriman — termasuk yang gagal & dilewati — tercatat dan bisa ditelusuri dari tab *Pengingat WA* |
| **Nomor PIC** | Diisi sendiri oleh pengguna di tab *Pengingat WA*, atau oleh Super Admin lewat form pengguna. Format bebas (`0812…`, `+62 812…`, `0812-3456-7890`) — sistem menormalkannya sendiri menjadi `62…` |

> Nomor WhatsApp adalah **data aplikasi, bukan konfigurasi** — disimpan di kolom
> `users.whatsapp_number` dan diisi lewat antarmuka, bukan ditulis di dalam kode
> atau `.env`. Dengan begitu nomor pribadi karyawan tidak pernah masuk ke riwayat
> git, dan tiap orang dapat mengganti atau menonaktifkan notifikasinya sendiri.

Selama `WHATSAPP_ENABLED=false`, **tidak ada pesan yang dikirim ke pihak mana pun**;
pengingat hanya dicatat di riwayat dengan status *dilewati*. Lihat `.env.example`
untuk daftar variabel lengkapnya.

Kirim manual dari terminal:

```bash
php artisan tasks:remind-whatsapp --dry-run   # lihat isi pesannya tanpa mengirim
php artisan tasks:remind-whatsapp             # kirim sungguhan
php artisan tasks:remind-whatsapp --force     # abaikan dedupe harian
```

> Penjadwal Laravel dijalankan oleh proses `scheduler` di `docker/supervisord.conf`
> (`php artisan schedule:work`). Tanpa proses itu, pengingat terjadwal tidak pernah jalan.

### Menyiapkan gateway swakelola

Tersedia dua pilihan gateway swakelola. **Pilih salah satu saja** — keduanya
tersambung ke WhatsApp lewat pemindaian QR, sehingga pengingat terkirim ke
**chat pribadi tiap PIC** dari nomor WhatsApp biasa milik perusahaan: tanpa
berlangganan penyedia pihak ketiga, dan tanpa batasan jendela 24 jam seperti
Cloud API resmi Meta.

| Driver | Proyek | Pilih bila |
|--------|--------|-----------|
| `go_whatsapp` | [go-whatsapp-web-multidevice](https://github.com/aldinokemal/go-whatsapp-web-multidevice) | Instalasi baru — paling ringan, satu container |
| `waha` | [WAHA — WhatsApp HTTP API](https://github.com/devlikeapro/waha) | WAHA sudah dipakai untuk hal lain (mis. integrasi Chatwoot) |

Panduan di bawah memakai `go_whatsapp`; untuk WAHA, ganti profil compose
`--profile whatsapp` menjadi `--profile waha`, dan pakai variabel
`WAHA_BASE_URL` / `WAHA_API_KEY` / `WAHA_SESSION` sebagai ganti `GOWA_*`.

**1. Isi kredensial di `.env.production`**

```bash
WHATSAPP_ENABLED=true
WHATSAPP_DRIVER=go_whatsapp
GOWA_BASE_URL=http://whatsapp:3000     # alamat internal antar-container
GOWA_USERNAME=gepbot
GOWA_PASSWORD=<password kuat & acak>
```

`GOWA_USERNAME`/`GOWA_PASSWORD` dipakai dua arah: menjadi `APP_BASIC_AUTH` yang
melindungi REST API gateway, sekaligus kredensial yang dipakai aplikasi untuk
memanggilnya.

**2. Nyalakan layanannya** (pakai profil compose `whatsapp`)

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml \
  --profile whatsapp up -d --build
```

**3. Pindai QR sekali** untuk menyambungkan nomor perusahaan. Port gateway sengaja
tidak dipublikasikan ke internet — sesi WhatsApp perusahaan tidak boleh bisa
dijangkau publik. Buka dasbornya lewat terowongan SSH:

```bash
ssh -L 3000:127.0.0.1:3000 user@server
# lalu buka http://127.0.0.1:3000 di peramban lokal
```

**4. Uji** lewat tab *Pengingat WA* → tombol **Uji**, atau dari terminal:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan tasks:remind-whatsapp --dry-run
```

> Sesi WhatsApp tersimpan di volume `gowa_storage`. **Jangan hapus volume itu** —
> menghapusnya berarti harus memindai ulang QR dan menyambungkan nomornya dari awal.

---

## 📄 Pemenuhan Dokumen Evidence (`tab Dokumen Bukti`)

Alur lengkap dari template sampai menjadi syarat penutupan task:

1. **Pilih template** dari dalam sebuah task (ikon dokumen di kartu Kanban, atau tombol
   *Buka Ruang Dokumen* di modal task). Data task — judul, PIC, divisi, proyek, tenggat,
   checklist — otomatis mengisi dokumen lewat penanda `{{...}}`.
2. **Sunting** isi dan kertas kerjanya dengan editor (teks tebal/miring, judul, daftar,
   tabel, perataan) beserta kolom isian tambahan milik template.
3. **Cetak / pratinjau** di tab baru — tampilannya sudah berbentuk lembar A4 siap cetak.
4. **Tanda tangani** lewat kanvas tanda tangan (jari, stylus, atau mouse). Setelah
   ditandatangani, dokumen **dibekukan** (tidak bisa diubah lagi) dan **otomatis menjadi PDF**.
5. **Jadikan bukti & tutup task** — PDF-nya langsung dipakai sebagai evidence penutupan.

**Template bawaan:** Berita Acara Penyelesaian Pekerjaan · Berita Acara Serah Terima Dokumen ·
Kertas Kerja Pemeriksaan · Kertas Kerja Monitoring Progres (landscape) ·
Laporan Pelaksanaan Tugas · Daftar Simak (Checklist) Verifikasi.

Template bawaan bersifat baca-saja; **duplikasikan** dulu untuk membuat versi milik
organisasi yang bisa disesuaikan (Super Admin / Manajer / Reviewer).

> Yang boleh menandatangani: **PIC task tersebut**, atau Super Admin / Manajer / Reviewer.
> Isi dokumen selalu disaring di server (daftar putih tag & atribut HTML) sebelum
> disimpan, dicetak, maupun dirender ke PDF.

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
  Console/Commands/   SendTaskWhatsAppReminders (perintah tasks:remind-whatsapp)
  Http/Controllers/   DashboardController, PalmOilSourceController, UnloadingPointController, AuthController,
                      TaskController, TaskProjectController, TaskCommentController, AdminUserController,
                      EvidenceDocumentController, EvidenceTemplateController, WhatsAppReminderController
  Models/             PalmOilSource, UnloadingPoint, PawmPLTU, User, Organization, Division,
                      Task, TaskProject, TaskChecklistItem, TaskComment,
                      EvidenceTemplate, EvidenceDocument, WhatsappNotification ...
  Services/           TaskAlertService        hitung alert deadline/prioritas/kadaluarsa
                      TaskWhatsAppReminder    susun & kirim pengingat WhatsApp
                      EvidenceDocumentService template → dokumen → tanda tangan → PDF
                      WhatsApp/               gateway + driver (log, go_whatsapp, waha,
                                              fonnte, wablas, cloud_api, webhook)
  Support/            PhoneNumber (normalisasi nomor), HtmlSanitizer (saring HTML dokumen)
config/whatsapp.php   saklar, driver, jadwal & kredensial notifikasi WhatsApp
database/
  migrations/         skema tabel (organizations, users, palm_oil_sources, unloading_points, tasks,
                      whatsapp_notifications, evidence_templates, evidence_documents, ...)
  seeders/            data contoh (admin, sumber, titik bongkar, PLTU, task demo) + template dokumen bawaan
resources/js/
  Components/MapViewer.jsx        komponen peta Leaflet (3 jenis marker)
  Components/UserFormModal.jsx    modal buat/edit pengguna (dipakai Tasks & Admin)
  Components/RichTextEditor.jsx   editor isi dokumen & kertas kerja
  Components/SignaturePad.jsx     kanvas tanda tangan (mouse/stylus/sentuh)
  Pages/Dashboard/                dashboard + peta
  Pages/Tasks/                    modul manajemen tugas (Kanban, Dashboard, Proyek, Dokumen Bukti,
                                  Pengingat WA, Tim, beserta modalnya)
  Pages/PalmOilSources/           CRUD sumber cangkang
  Pages/UnloadingPoints/          CRUD titik bongkar (customer)
resources/views/evidence/document.blade.php   tata letak dokumen untuk cetak & PDF
routes/web.php        seluruh route
routes/console.php    penjadwalan pengingat WhatsApp harian
```

---

## ⚙️ Catatan Konfigurasi

- Database dev: MySQL `gep_erp`, user `root` tanpa password (default Laragon), lihat `.env`.
- Session & cache: driver `file` (tanpa Redis).
- Folder tidak boleh mengandung karakter `#` (kendala Vite).
- Notifikasi WhatsApp mati secara bawaan (`WHATSAPP_ENABLED=false`) — isi kredensial gateway
  di `.env` sebelum menyalakannya. Daftar variabelnya ada di `.env.example`.
- PDF dokumen bukti dihasilkan `barryvdh/laravel-dompdf` (butuh ekstensi PHP `dom`, `mbstring`, `gd` —
  ketiganya sudah ada di image Docker aplikasi ini).

---

**Versi:** 2.1 · Modul Sumber Cangkang + Titik Bongkar + Peta + Manajemen Tugas
(pengingat WhatsApp & pemenuhan dokumen evidence)
**Dibuat untuk:** PT Geosys Energi Prima
