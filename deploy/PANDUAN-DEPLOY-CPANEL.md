# Panduan Deploy ke Hosting cPanel
**PT Geosys Energi Prima — Aplikasi ERP**

Panduan ini untuk hosting langganan biasa (cPanel) **tanpa perlu akses SSH**. Seluruh kebutuhan aplikasi sudah dibundel di dalam paket, termasuk folder `vendor/` — jadi tidak perlu menjalankan Composer di server.

Perkiraan waktu: **30–45 menit**.

---

## Sebelum Mulai — Yang Perlu Disiapkan

| Kebutuhan | Keterangan |
|---|---|
| Akun cPanel | Beserta nama pengguna & kata sandi |
| Domain / subdomain | Contoh: `erp.namadomain.co.id` |
| PHP 8.2 atau 8.3 | Diatur lewat menu **MultiPHP Manager** di cPanel |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo` (umumnya sudah aktif) |
| Ruang disk | ± 250 MB |

---

## Langkah 1 — Rakit Paket di Komputer Lokal

Jalankan dari folder proyek:

```bash
powershell -ExecutionPolicy Bypass -File deploy\build-paket-deploy.ps1
```

Hasilnya ada di `deploy\paket-upload\`:

```
paket-upload/
├── gep-erp/         → inti aplikasi (diunggah ke luar public_html)
├── public_html/     → berkas yang bisa diakses publik
├── database/
│   └── gep_erp_master.sql
└── PANDUAN-DEPLOY-CPANEL.md
```

> **Struktur ini memisahkan inti aplikasi dari folder publik.** Berkas `.env` (berisi kata sandi database) berada di luar `public_html`, sehingga tidak bisa diakses lewat browser.

---

## Langkah 2 — Buat Database di cPanel

1. Buka **MySQL® Databases**.
2. **Create New Database** → nama: `gep_erp` → cPanel akan menamainya `namaakun_gep_erp`.
3. **Add New User** → buat user + kata sandi kuat → catat keduanya.
4. **Add User To Database** → pilih user & database tadi → centang **ALL PRIVILEGES** → Make Changes.

Catat tiga nilai ini untuk Langkah 4:

```
DB_DATABASE = namaakun_gep_erp
DB_USERNAME = namaakun_namauser
DB_PASSWORD = (kata sandi yang dibuat)
```

---

## Langkah 3 — Impor Database Master

1. Buka **phpMyAdmin** dari cPanel.
2. Pilih database `namaakun_gep_erp` di panel kiri.
3. Tab **Import** → **Choose File** → pilih `database/gep_erp_master.sql`.
4. Klik **Go**, tunggu sampai muncul konfirmasi berhasil.

Isi database master:

- Seluruh struktur tabel (29 tabel) — status migrasi sudah tercatat, jadi tidak perlu `artisan migrate`
- Chart of Accounts lengkap (termasuk PPN Masukan, PPN Keluaran, Utang Bank & Investor)
- Role & permission (super_admin, drafter, reviewer, approval, external) beserta hak akses granular per menu
- Data master: 43 sumber cangkang, 56 titik bongkar, 3 titik dermaga, 8 PLTU tujuan, daftar divisi
- Satu akun Super Admin

> **Tanpa data transaksi** — jurnal, dokumen, dan task dimulai dari nol di server produksi.

> Jika muncul error `max_allowed_packet`, unggah berkas SQL lewat File Manager ke folder home, lalu di phpMyAdmin gunakan menu **Import → Browse your computer** dengan berkas terkompresi `.zip`.

---

## Langkah 4 — Siapkan Berkas `.env`

Di folder `gep-erp/` terdapat `.env.CONTOH-ISI-DULU`.

1. Buka dengan editor teks (Notepad++ / VS Code).
2. Isi bagian bertanda `<<< ISI >>>` — terutama `APP_URL` dan ketiga nilai database dari Langkah 2.
3. **Ganti nama berkas menjadi `.env`** (persis, diawali titik, tanpa akhiran).

`APP_KEY` dikosongkan dulu — akan diisi di Langkah 6.

---

## Langkah 5 — Unggah Berkas

1. Kompres `gep-erp` dan `public_html` masing-masing menjadi `.zip`.
2. Di cPanel buka **File Manager**.
3. Unggah `gep-erp.zip` ke folder home (`/home/namaakun/`), lalu **Extract**.
4. Unggah `public_html.zip` ke dalam `public_html`, lalu **Extract** (timpa berkas yang ada).

Hasil akhir di server:

```
/home/namaakun/
├── gep-erp/          ← inti aplikasi (app, config, vendor, .env, storage)
└── public_html/      ← index.php, .htaccess, build/, images/
```

> **Untuk subdomain:** bila aplikasi dipasang di subdomain (mis. `erp.namadomain.co.id`) yang document root-nya `/home/namaakun/erp`, unggah isi `public_html` ke folder `erp` tersebut, lalu buka `erp/index.php` dan ubah baris `$appRoot` menjadi `__DIR__ . '/../gep-erp'` (sesuaikan bila kedalaman foldernya berbeda).

### Atur Izin Folder

Lewat File Manager, klik kanan → **Change Permissions**:

| Folder | Izin |
|---|---|
| `gep-erp/storage` (beserta isinya, centang *Recurse*) | `755` |
| `gep-erp/bootstrap/cache` | `755` |

---

## Langkah 6 — Buat APP_KEY

`APP_KEY` adalah kunci enkripsi aplikasi. Pilih salah satu cara:

**Cara A — lewat Terminal cPanel** (bila tersedia di menu *Advanced → Terminal*):

```bash
cd ~/gep-erp && php artisan key:generate
```

**Cara B — tanpa terminal:**

1. Buat berkas `public_html/buat-key.php` berisi:

   ```php
   <?php echo 'base64:' . base64_encode(random_bytes(32));
   ```

2. Buka `https://domain-anda/buat-key.php` di browser.
3. Salin hasilnya (mis. `base64:xxxx…`) ke baris `APP_KEY=` di `.env`.
4. **Hapus `buat-key.php`** dari server.

---

## Langkah 7 — Sambungkan Folder Storage

Agar lampiran jurnal & tanda tangan dokumen bisa ditampilkan:

1. Buka `https://domain-anda/link-storage.php` di browser.
2. Bila muncul **BERHASIL**, **hapus berkas `link-storage.php`** dari `public_html`.

Bila gagal (hosting mematikan `symlink()`), buat folder `storage` di dalam `public_html` lewat File Manager, lalu salin isi `gep-erp/storage/app/public` ke dalamnya.

---

## Langkah 8 — Uji Coba

Buka `https://domain-anda`. Halaman login akan muncul.

**Akun Super Admin bawaan:**

```
Email    : admin@pt-gep.com
Password : Admin@12345
```

> ⚠️ **Segera ganti kata sandi ini setelah login pertama** — kredensial di atas berasal dari lingkungan pengembangan dan sudah diketahui umum. Ganti lewat menu **Manajemen User → Edit pengguna**, dan sebaiknya ganti pula alamat emailnya ke email resmi perusahaan.

Yang perlu diperiksa setelah login:

- [ ] Peta dashboard tampil beserta titik sumber & titik bongkar
- [ ] Menu **Akuntansi → Daftar Akun** memuat Chart of Accounts
- [ ] Menu **Akuntansi → Kontrol PPN** terbuka
- [ ] Menu **Manajemen User** dapat menambah pengguna baru
- [ ] Modul **Dokumen** dapat membuat dokumen & mencetak PDF

---

## Langkah 9 — Pengamanan Akhir

1. Pastikan `.env` **tidak** berada di dalam `public_html`.
2. Pastikan `APP_DEBUG=false` di `.env`.
3. Hapus `buat-key.php` dan `link-storage.php` dari `public_html`.
4. Aktifkan **SSL** (cPanel → *SSL/TLS Status* → AutoSSL) lalu pastikan `APP_URL` memakai `https://`.
5. Aktifkan backup otomatis database lewat cPanel → *Backup*.

---

## Bila Terjadi Masalah

| Gejala | Penyebab & Solusi |
|---|---|
| Layar putih / HTTP 500 | Cek `gep-erp/storage/logs/laravel.log`. Umumnya `APP_KEY` kosong atau izin folder `storage` belum `755`. |
| "Folder aplikasi tidak ditemukan" | Nama folder aplikasi bukan `gep-erp`. Sesuaikan `$appRoot` di `public_html/index.php`. |
| Halaman tampil tanpa gaya/CSS | Folder `public_html/build` belum terunggah, atau berkas `hot` ikut terunggah — **hapus `public_html/hot`** bila ada. |
| Semua URL selain beranda → 404 | `.htaccess` belum terunggah (berkas berawalan titik tersembunyi — aktifkan *Show Hidden Files* di File Manager) atau `mod_rewrite` nonaktif. |
| "SQLSTATE[HY000] [1045] Access denied" | Nama database/user/sandi di `.env` keliru, atau user belum ditambahkan ke database (Langkah 2 poin 4). |
| "419 Page Expired" saat login | Izin folder `gep-erp/storage/framework/sessions` belum `755`. |
| Peta tidak muncul | Peta memuat ubin dari OpenStreetMap — pastikan hosting tidak memblokir koneksi keluar. |

---

## Pembaruan Aplikasi di Kemudian Hari

Untuk mengunggah versi terbaru **tanpa menghapus data**:

1. Rakit ulang paket (Langkah 1).
2. Unggah ulang folder `app`, `config`, `resources`, `routes`, `database`, dan `vendor` ke `gep-erp/` (timpa).
3. Unggah ulang isi `public_html` **kecuali** `.env`.
4. **Jangan** impor ulang `gep_erp_master.sql` — data akan tertimpa.
5. Bila ada perubahan struktur database (migration baru), jalankan lewat Terminal cPanel:
   ```bash
   cd ~/gep-erp && php artisan migrate --force
   ```
   Bila tidak ada Terminal, jalankan migration di lokal lalu ekspor hanya perubahan tabelnya lewat phpMyAdmin.

---

## Catatan Teknis

- **Folder `vendor/` ikut dibundel** sehingga tidak perlu Composer di server. Konsekuensinya paket berukuran agak besar dan masih memuat paket pengembangan. Bila hosting menyediakan SSH, jalankan `composer install --no-dev --optimize-autoloader` di server untuk memperkecil ukuran.
- **Queue memakai driver `sync`** — pekerjaan latar dijalankan langsung dalam request. Cocok untuk shared hosting yang tidak mengizinkan proses daemon.
- **Session & cache memakai driver `file`** — tidak memerlukan Redis.
- **Zona waktu** diatur ke `Asia/Jakarta` lewat `.env`.
