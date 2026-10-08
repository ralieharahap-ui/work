# Handbook Deploy Aplikasi ERP PT GEP ke Web
**Hosting cPanel Dewaweb + DNS Cloudflare — panduan langkah demi langkah, bahasa sederhana**

> Handbook ini menjelaskan cara memasang aplikasi akuntansi/ERP PT Geosys Energi Prima di internet sampai bisa dibuka di `https://geosys-ep.id`.
> Tanda **✅** = langkah sudah dikerjakan pada pemasangan pertama (8 Okt 2026). Tanda **⬜** = masih harus dikerjakan.
> Handbook ini **tidak memuat kata sandi apa pun**. Kata sandi disimpan terpisah oleh penanggung jawab IT.

---

## Daftar Isi
0. [Gambaran Besar — Bagaimana Semuanya Tersambung](#0-gambaran-besar)
1. [Data Penting yang Dipakai](#1-data-penting)
2. [Bagian A — Menyiapkan Hosting cPanel](#bagian-a--menyiapkan-hosting-cpanel)
3. [Bagian B — Mengatur DNS di Cloudflare](#bagian-b--mengatur-dns-di-cloudflare)
4. [Bagian C — Mengaktifkan HTTPS (Gembok Hijau)](#bagian-c--mengaktifkan-https)
5. [Bagian D — Uji Coba Setelah Online](#bagian-d--uji-coba-setelah-online)
6. [Bagian E — Pengamanan Akhir](#bagian-e--pengamanan-akhir)
7. [Bagian F — Memperbarui Aplikasi di Kemudian Hari](#bagian-f--memperbarui-aplikasi)
8. [Bagian G — Jika Terjadi Masalah](#bagian-g--jika-terjadi-masalah)
9. [Kamus Istilah](#kamus-istilah)

---

## 0. Gambaran Besar

Bayangkan tiga pihak ini seperti **alamat rumah, buku telepon, dan rumahnya sendiri**:

```
  Pengguna mengetik  geosys-ep.id
            │
            ▼
  ┌──────────────────────┐     "Buku telepon" internet.
  │  CLOUDFLARE (DNS)    │     Memberi tahu browser: geosys-ep.id ada di IP 103.185.53.46
  │  + pelindung/proxy   │     Sekaligus jadi tameng (anti-serangan, HTTPS, cache gambar).
  └──────────┬───────────┘
             ▼
  ┌──────────────────────┐     "Rumah" aplikasi.
  │  HOSTING DEWAWEB     │     Server cPanel tempat aplikasi & database berjalan.
  │  (cPanel, jkt06)     │
  │   ├─ gep-erp/        │  ← inti aplikasi (kode, .env, data upload) — TIDAK bisa diakses publik
  │   ├─ public_html/    │  ← pintu depan (index.php, gambar, CSS/JS)
  │   └─ MySQL database  │  ← semua data akuntansi
  └──────────────────────┘

  Registrar domain (PANDI/.id lewat Dewaweb) hanya menyimpan satu hal:
  "untuk domain ini, tanyakan ke nameserver Cloudflare".
```

**Urutan kerjanya:**
1. Pasang aplikasi di hosting (Bagian A).
2. Arahkan domain ke hosting lewat Cloudflare (Bagian B).
3. Aktifkan HTTPS (Bagian C).
4. Uji, amankan, selesai (Bagian D–E).

---

## 1. Data Penting

| Item | Nilai |
|---|---|
| Domain | `geosys-ep.id` |
| Panel hosting | cPanel Dewaweb — `https://jkt06.dewaweb.com:2083` |
| Akun cPanel | `geosyse1` |
| **IP server untuk website** | **`103.185.53.46`** ⚠️ (bukan 103.185.52.93) |
| Nameserver Cloudflare | `cortney.ns.cloudflare.com` dan `thaddeus.ns.cloudflare.com` |
| Folder aplikasi | `/home/geosyse1/gep-erp` |
| Folder publik | `/home/geosyse1/public_html` |
| Database | `geosyse1_gep_erp` |
| User database | `geosyse1_gep` |
| Versi PHP | 8.3 (diatur lewat *Select PHP Version* / CloudLinux) |

> 💡 **Cara memastikan IP server sendiri:** cPanel → kolom kanan **General Information** → *Shared IP Address*. Atau cPanel → **Domains** → klik domain.

---

## Bagian A — Menyiapkan Hosting cPanel

### A1. Rakit paket aplikasi di komputer kantor ✅
Di folder proyek, jalankan:
```bash
powershell -ExecutionPolicy Bypass -File deploy\build-paket-deploy.ps1
```
Hasilnya ada di `deploy\paket-upload\` (folder `gep-erp`, `public_html`, dan `database\gep_erp_master.sql`).

> Folder `vendor` di paket berukuran ±340 MB karena ikut membawa alat pengembang. **Tidak perlu diunggah** — di server cukup menjalankan Composer (langkah A5) sehingga hanya ±41 MB.

### A2. Pilih versi PHP 8.3 + aktifkan ekstensi ✅
1. cPanel → **Select PHP Version** (bagian *Software*).
2. *Current PHP version* → pilih **8.3** → **Set as current**.
3. Di daftar *Extensions*, pastikan tercentang: `bcmath`, `fileinfo`, `gd`, `intl`, `mbstring`, `nd_pdo_mysql` (pdo_mysql), `opcache`, `sodium`, `zip`, `dom`, `xmlreader`, `xmlwriter`, `phar`.

> ⚠️ **`fileinfo` wajib.** Tanpa ekstensi ini, fitur upload lampiran jurnal/dokumen akan error.

### A3. Buat database ✅ (sebagian)
cPanel → **MySQL® Databases**:
1. **Create New Database** → ketik `gep_erp` → menjadi `geosyse1_gep_erp`. ✅
2. **Add New User** → username `gep` (menjadi `geosyse1_gep`) → buat kata sandi kuat (pakai tombol *Password Generator*) → **simpan kata sandi di tempat aman** → *Create User*. ⬜
3. **Add User To Database** → pilih user `geosyse1_gep` + database `geosyse1_gep_erp` → **Add** → centang **ALL PRIVILEGES** → *Make Changes*. ⬜

### A4. Unggah berkas aplikasi ✅
Ada dua cara — pilih salah satu.

**Cara mudah (File Manager):**
1. Kompres folder `gep-erp` (tanpa `vendor`) dan `public_html` menjadi `.zip`.
2. cPanel → **File Manager** → buka folder home (`/home/geosyse1`).
3. **Upload** zip → klik kanan → **Extract**.
4. Pindahkan isi `public_html` hasil ekstrak ke folder `public_html` asli.

**Cara cepat (SSH, untuk tim IT):**
```bash
scp app.zip master.sql.gz geosyse1@jkt06.dewaweb.com:~/
ssh geosyse1@jkt06.dewaweb.com
unzip -q app.zip
```

> 📌 **Jangan timpa** `.htaccess`, `php.ini`, dan `.user.ini` bawaan cPanel di `public_html` — gabungkan saja. Aturan aplikasi ditaruh di atas, blok `# BEGIN cPanel-generated ...` tetap di bawah.

Hasil akhir di server:
```
/home/geosyse1/
├── gep-erp/        ← app, config, routes, vendor, storage, .env
└── public_html/    ← index.php, .htaccess, build/, images/, storage (tautan)
```

### A5. Pasang pustaka PHP (Composer) ✅
cPanel → **Terminal** (atau SSH), lalu ketik:
```bash
cd ~/gep-erp
mkdir -p ~/bin
curl -sS https://getcomposer.org/installer | php -d allow_url_fopen=On -- --install-dir=$HOME/bin --filename=composer
php -d allow_url_fopen=On -d memory_limit=1G ~/bin/composer install --no-dev --optimize-autoloader --no-interaction
```
Tunggu sampai muncul `Generating optimized autoload files`.

### A6. Isi berkas `.env` (pengaturan rahasia aplikasi) ✅ (sebagian)
Berkas `/home/geosyse1/gep-erp/.env` sudah dibuat. Yang tersisa hanya kata sandi database: ⬜
1. cPanel → **File Manager** → folder `gep-erp`.
2. Kanan atas **Settings** → centang **Show Hidden Files (dotfiles)** → Save. (Berkas berawalan titik itu tersembunyi.)
3. Klik kanan `.env` → **Edit**.
4. Cari baris `DB_PASSWORD=`, lalu ketik kata sandi dari langkah A3 **tepat setelah tanda `=`**, tanpa spasi.
   *Jika kata sandi mengandung `#` atau spasi, apit dengan tanda kutip:* `DB_PASSWORD="abc#123"`.
5. **Save Changes**.

Isi penting `.env` (sudah terpasang):
```
APP_ENV=production
APP_DEBUG=false            ← wajib false di server (agar pesan error tidak bocor)
APP_URL=https://geosys-ep.id
APP_KEY=base64:...         ← kunci enkripsi, JANGAN diubah/dibagikan
DB_DATABASE=geosyse1_gep_erp
DB_USERNAME=geosyse1_gep
SESSION_SECURE_COOKIE=true ← login hanya lewat https
```

> ⚠️ `APP_KEY` sudah dibuat dengan `php artisan key:generate`. **Jangan pernah membuat ulang** setelah aplikasi dipakai — data terenkripsi & sesi login akan rusak.

### A7. Impor database awal ⬜
Setelah A3 dan A6 selesai:

**Cara mudah (phpMyAdmin):**
1. cPanel → **phpMyAdmin** → klik `geosyse1_gep_erp` di kiri.
2. Tab **Import** → *Choose File* → `gep_erp_master.sql` → **Go**.

**Cara cepat (Terminal):** sudah disiapkan skrip yang membaca kredensial dari `.env` tanpa menampilkannya:
```bash
bash ~/_deploy/import-db.sh
```
Hasil normal: jumlah tabel ±32, akun COA aktif 168, user 1.

> ⚠️ Impor database master **hanya sekali** di awal. Mengimpor ulang akan menimpa semua data transaksi!

### A8. Folder penyimpanan & tautan storage ✅
```bash
cd ~/gep-erp
chmod -R 755 storage bootstrap/cache
ln -sfn ~/gep-erp/storage/app/public ~/public_html/storage
```
Tautan ini membuat lampiran & tanda tangan dokumen bisa tampil di browser.

### A9. Percepat aplikasi (cache konfigurasi) ⬜
Jalankan **setiap kali** `.env` atau kode diubah:
```bash
cd ~/gep-erp
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Bagian B — Mengatur DNS di Cloudflare

### B1. Daftarkan domain di Cloudflare ✅
1. Masuk ke `dash.cloudflare.com` → **Add a site** → ketik `geosys-ep.id` → pilih paket **Free**.
2. Cloudflare memberi 2 nameserver: **`cortney.ns.cloudflare.com`** dan **`thaddeus.ns.cloudflare.com`**.

### B2. Ganti nameserver di registrar domain ✅ (menunggu aktif)
1. Masuk ke client area Dewaweb → **Domains** → `geosys-ep.id` → **Manage Nameservers**.
2. Pilih *Use custom nameservers* → isi:
   - Nameserver 1: `cortney.ns.cloudflare.com`
   - Nameserver 2: `thaddeus.ns.cloudflare.com`
   - Kosongkan nameserver 3 dan seterusnya.
3. Simpan.

> ⏳ **Masa tunggu (propagasi):** domain `.id` biasanya aktif dalam 1–24 jam, maksimal 48 jam. Cloudflare akan mengirim email *"geosys-ep.id is now active"*. Selama menunggu, website belum bisa dibuka lewat nama domain — ini normal.

**Cara cek sudah aktif atau belum** (Command Prompt Windows):
```bash
nslookup -type=ns geosys-ep.id 1.1.1.1
```
Sudah aktif bila hasilnya menyebut `cortney.ns.cloudflare.com` dan `thaddeus.ns.cloudflare.com`.

### B3. Isi catatan DNS di Cloudflare ⬜ (PENTING)
Cloudflare → pilih `geosys-ep.id` → menu **DNS → Records**.

> ⚠️ **Temuan pengecekan 8 Okt 2026:** di Cloudflare, `www` masih mengarah ke **103.185.52.93** (IP lama/parkir, membalas *404 Not Found*), dan domain utama `geosys-ep.id` **belum punya catatan A**. Keduanya harus dibetulkan seperti tabel di bawah. Hapus catatan lain yang mengarah ke 103.185.52.93.

| Type | Name | Content / Target | Proxy status | Fungsi |
|---|---|---|---|---|
| **A** | `@` | `103.185.53.46` | ☁️ **DNS only (abu-abu)** dulu → nanti oranye | Website utama |
| **CNAME** | `www` | `geosys-ep.id` | ☁️ sama dengan `@` | `www.geosys-ep.id` |
| **A** | `mail` | `103.185.53.46` | ☁️ **DNS only (abu-abu) — selamanya** | Server email |
| **A** | `cpanel` | `103.185.53.46` | ☁️ DNS only (abu-abu) | Akses cPanel via domain (opsional) |
| **MX** | `@` | `mail.geosys-ep.id` (priority 0) | — | Penerimaan email |
| **TXT** | `@` | salin dari cPanel → *Email Deliverability* (SPF) | — | Anti email palsu |
| **TXT** | `default._domainkey` | salin dari cPanel → *Email Deliverability* (DKIM) | — | Tanda tangan email |

Cara menambah satu catatan: **Add record** → pilih *Type* → isi *Name* & *Content* → atur awan *Proxy status* → **Save**.

> 💡 **Kenapa abu-abu dulu?** Supaya cPanel bisa menerbitkan sertifikat HTTPS gratis (AutoSSL) langsung ke server. Setelah gembok aktif (Bagian C), ubah `@` dan `www` menjadi **oranye (Proxied)**.
>
> ⚠️ **Catatan email (`mail`, MX) jangan pernah dibuat oranye** — email tidak bisa lewat proxy Cloudflare.
>
> 💡 **SPF/DKIM:** cPanel → **Email Deliverability** → klik *Manage* pada `geosys-ep.id` → salin nilai *Suggested* SPF & DKIM ke Cloudflare. Hanya perlu bila memakai email `@geosys-ep.id`.

### B4. Pengaturan Cloudflare yang dianjurkan ⬜
| Menu Cloudflare | Pengaturan | Alasan |
|---|---|---|
| **SSL/TLS → Overview** | **Full (strict)** | ⚠️ JANGAN pilih *Flexible* — menyebabkan *redirect loop* & gagal login |
| SSL/TLS → Edge Certificates | **Always Use HTTPS: On** | Semua kunjungan dipaksa https |
| SSL/TLS → Edge Certificates | Minimum TLS Version: **1.2** | Keamanan |
| SSL/TLS → Edge Certificates | Automatic HTTPS Rewrites: On | Mencegah *mixed content* |
| **Speed → Optimization** | **Rocket Loader: Off** | Rocket Loader bisa merusak tampilan React/Inertia aplikasi |
| Caching → Configuration | Caching Level: Standard | HTML aplikasi tidak di-cache (aman untuk data akuntansi) |
| Security → Settings | Security Level: Medium; Bot Fight Mode: On | Tameng serangan |
| Network | WebSockets: On (default) | — |

> 🛡️ **Opsional — kunci halaman login:** Security → WAF → *Rate limiting rules* → buat aturan untuk URL `/login` (misal maksimal 10 permintaan/menit per IP) agar tebak-tebakan kata sandi diblokir.

---

## Bagian C — Mengaktifkan HTTPS

Urutan aman: **sertifikat di server dulu → baru proxy Cloudflare.**

### C1. Terbitkan sertifikat di cPanel (AutoSSL) ⬜
Syarat: B2 sudah aktif dan B3 sudah diisi (status `@` dan `www` masih **abu-abu**).
1. cPanel → **SSL/TLS Status**.
2. Centang `geosys-ep.id` dan `www.geosys-ep.id` → **Run AutoSSL**.
3. Tunggu 2–10 menit, lalu muat ulang. Status harus berubah menjadi gembok hijau *"AutoSSL Domain Validated"*.

### C2. Nyalakan proxy Cloudflare ⬜
1. Cloudflare → DNS → ubah `@` dan `www` menjadi **oranye (Proxied)**.
2. SSL/TLS → **Full (strict)**.
3. Buka `https://geosys-ep.id` → harus muncul halaman login dengan gembok.

### C3. Rencana cadangan: Cloudflare Origin Certificate
Gunakan bila AutoSSL gagal:
1. Cloudflare → SSL/TLS → **Origin Server** → **Create Certificate** → biarkan default (15 tahun) → Create.
2. Salin **Origin Certificate** dan **Private Key**. Private key hanya tampil sekali — simpan rahasia.
3. cPanel → **SSL/TLS** → *Manage SSL sites* → pilih domain → tempel sertifikat (CRT) & private key (KEY) → **Install Certificate**.
4. Cloudflare SSL/TLS tetap **Full (strict)**, dan `@`/`www` harus **oranye**. Sertifikat origin hanya dipercaya lewat Cloudflare.

---

## Bagian D — Uji Coba Setelah Online

### D1. Login pertama
1. Buka `https://geosys-ep.id` → halaman login muncul.
2. Masuk dengan akun Super Admin bawaan (kata sandi awal diberikan terpisah).
3. **Segera ganti kata sandi & email admin** lewat **Manajemen User → Edit**.

### D2. Daftar periksa fitur (centang satu per satu)
**Dasar**
- [ ] Halaman login tampil rapi (CSS/logo muncul), ada gembok HTTPS
- [ ] Login & logout berhasil; tidak ada tombol *Daftar/Sign up* (memang dihapus)
- [ ] Dashboard: KPI, grafik, dan peta (titik sumber & titik bongkar) tampil
- [ ] Halaman **Menu Aplikasi** & sidebar berwarna tampil; pencarian menu `Ctrl + /` berfungsi

**Akuntansi**
- [ ] Daftar Akun memuat COA PSAK 2026 (168 akun aktif, 13 header)
- [ ] Buat jurnal → simpan draft → ajukan → approve → posted
- [ ] Jurnal ke akun *header* ditolak (pesan: "Akun tidak dapat dipakai posting")
- [ ] Upload lampiran jurnal (PDF/JPG) berhasil & bisa dibuka
- [ ] Buku Besar, Neraca Saldo, Neraca Lajur terbuka
- [ ] Laba Rugi bertahap (Laba Bruto → Laba Usaha → Laba Bersih) & Neraca seimbang
- [ ] Kontrol PPN, Kreditur Pendanaan, Aset Tetap terbuka

**Dokumen & Tugas**
- [ ] Buat dokumen (Surat Resmi, PD, Reimbursement) → upload lampiran → cetak PDF
- [ ] Alur review → approval → **Sign** memunculkan QR kecil di kanan atas
- [ ] Scan QR dengan HP → halaman verifikasi hijau **"Dokumen Sah"** tampil (tanpa login)
- [ ] Manajemen Task terbuka; tugas bisa dibuat & diubah statusnya

**Pengguna**
- [ ] Super Admin bisa menambah, edit, menonaktifkan user
- [ ] User dengan role terbatas hanya melihat menu sesuai haknya

**Jaringan & keamanan**
- [ ] `http://geosys-ep.id` otomatis pindah ke `https://`
- [ ] `www.geosys-ep.id` terbuka normal
- [ ] `https://geosys-ep.id/.env` → **harus 404** (berkas rahasia tidak boleh terbaca)
- [ ] Buka dari HP (data seluler) & dari jaringan kantor — keduanya lancar

### D3. Cek cepat dari komputer (opsional, untuk tim IT)
```bash
curl -I https://geosys-ep.id/login
curl -I https://geosys-ep.id/.env
```
Yang pertama harus `200`, yang kedua harus `404`.

---

## Bagian E — Pengamanan Akhir

1. `.env` berada di `gep-erp/`, **bukan** di `public_html`. ✅
2. `APP_DEBUG=false`. ✅
3. Hapus berkas bantu `buat-key.php` / `link-storage.php` dari `public_html` bila ada. ✅ (tidak diunggah)
4. Ganti kata sandi Super Admin bawaan. ⬜
5. **Backup otomatis:** cPanel → **Backup** / *JetBackup* → pastikan backup harian aktif. Sebaiknya unduh juga backup database mingguan (phpMyAdmin → Export) ke Google Drive kantor. ⬜
6. **Kunci SSH deploy:** setelah pemasangan selesai, kunci SSH `gep_deploy` boleh dicabut di cPanel → **SSH Access → Manage SSH Keys → Deauthorize**. Daftarkan lagi saat ada pembaruan. ⬜
7. Aktifkan **Two-Factor Authentication** untuk login cPanel (cPanel → *Two-Factor Authentication*) dan Cloudflare (My Profile → Authentication). ⬜

---

## Bagian F — Memperbarui Aplikasi

Saat ada fitur baru dari tim pengembang:

1. **Backup dulu** database (phpMyAdmin → Export) dan folder `gep-erp`.
2. Rakit paket baru (langkah A1).
3. Unggah & timpa folder `app`, `bootstrap/app.php`, `config`, `database`, `resources`, `routes`, `composer.json`, `composer.lock` di `gep-erp/`, serta `build/` & `images/` di `public_html/`.
   **Jangan timpa:** `.env`, folder `storage/`, `vendor/`.
4. Jalankan di Terminal:
   ```bash
   cd ~/gep-erp
   php artisan down                      # mode perawatan
   php -d allow_url_fopen=On ~/bin/composer install --no-dev --optimize-autoloader --no-interaction
   php artisan migrate --force           # hanya menambah perubahan tabel, data aman
   php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan up
   ```
5. Bila tampilan belum berubah: Cloudflare → **Caching → Purge Everything**, lalu tekan `Ctrl+F5` di browser.

> ⚠️ **Jangan pernah** mengimpor ulang `gep_erp_master.sql` ke server yang sudah berisi data.

---

## Bagian G — Jika Terjadi Masalah

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Domain tidak bisa dibuka sama sekali | Nameserver belum aktif | Tunggu propagasi; cek dengan `nslookup -type=ns geosys-ep.id 1.1.1.1` |
| **404 Not Found** halaman putih polos | DNS mengarah ke IP salah (103.185.52.93) | Ubah catatan A ke **103.185.53.46** (B3) |
| **Error 521 / 522** (Cloudflare) | Server tidak menjawab / IP salah | Cek IP di DNS; cek server di cPanel |
| **Error 525 / 526** (Cloudflare) | SSL server belum ada/tidak valid | Jalankan AutoSSL (C1) atau pasang Origin Certificate (C3) |
| **ERR_TOO_MANY_REDIRECTS** | SSL Cloudflare di mode *Flexible* | Ubah ke **Full (strict)** |
| **HTTP 500 / "Server Error"** | `.env` salah, kata sandi DB salah, izin folder | Lihat log: `gep-erp/storage/logs/laravel-*.log` |
| "SQLSTATE[HY000] [1045] Access denied" | `DB_PASSWORD`/user salah, atau user belum ditambahkan ke DB | Ulangi A3 poin 3 dan A6; lalu `php artisan config:cache` |
| **419 Page Expired** saat login | Sesi/cookie bermasalah | Pastikan buka via `https://`; izin `storage/framework/sessions` 755; hapus cookie browser |
| Tampilan tanpa warna/CSS | Folder `public_html/build` tidak ada, atau ada berkas `public_html/hot` | Unggah ulang `build/`; hapus `hot` |
| Halaman blank/tombol tidak jalan setelah pakai Cloudflare | Rocket Loader aktif | Matikan Rocket Loader (B4), Purge cache |
| Upload lampiran gagal | Ekstensi `fileinfo` mati / batas ukuran upload | Aktifkan `fileinfo` (A2); naikkan `upload_max_filesize` di *Select PHP Version → Options* |
| Lampiran tidak tampil (404) | Tautan `public_html/storage` hilang | Ulangi A8 |
| Email tidak terkirim/masuk | Record `mail`/MX diproxy (oranye) | Jadikan **DNS only (abu-abu)** |
| Perubahan `.env` tidak berpengaruh | Konfigurasi masih ter-cache | `php artisan config:cache` |

---

## Kamus Istilah

| Istilah | Arti sederhana |
|---|---|
| **Domain** | Nama alamat website, contoh `geosys-ep.id` |
| **DNS** | "Buku telepon" internet yang menerjemahkan nama domain menjadi alamat IP |
| **Nameserver** | Pengelola buku telepon untuk domain kita — sekarang dipegang Cloudflare |
| **IP address** | Nomor alamat server, contoh `103.185.53.46` |
| **Propagasi** | Waktu tunggu sampai perubahan DNS dikenal di seluruh internet |
| **Proxy oranye / abu-abu** | Oranye = lalu lintas lewat tameng Cloudflare; abu-abu = langsung ke server |
| **SSL / HTTPS** | Enkripsi; tanda gembok di browser |
| **AutoSSL** | Fitur cPanel yang menerbitkan sertifikat HTTPS gratis otomatis |
| **cPanel** | Panel kontrol hosting (file, database, email, PHP) |
| **`.env`** | Berkas pengaturan rahasia aplikasi (kata sandi DB, kunci enkripsi) |
| **Composer / vendor** | Pengunduh pustaka PHP / folder hasil unduhannya |
| **Migration** | Skrip perubahan struktur tabel database yang aman untuk data lama |
| **SSH / Terminal** | Jalur ketik-perintah langsung ke server |
