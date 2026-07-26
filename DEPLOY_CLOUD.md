# ☁️ Panduan Deploy Online — Aplikasi Sumber Biomassa PT GEP

Panduan agar aplikasi bisa **diakses dari mana saja lewat internet** (HP, tablet, laptop) via sebuah domain HTTPS. Menggunakan **Docker** sehingga bisa jalan di server/VPS mana pun.

> Saya (asisten) tidak bisa menyewa server, membuat akun, atau membayar untuk Anda — langkah itu Anda lakukan sendiri. Semua konfigurasi sudah disiapkan; Anda tinggal menjalankan perintah di bawah.

---

## Ringkasan arsitektur
```
Internet ──HTTPS──> [Caddy]  ──> [App: Nginx+PHP-FPM (Laravel)] ──> [MySQL 8]
              (sertifikat SSL otomatis Let's Encrypt)
```
Semua berjalan sebagai 3 kontainer Docker di satu server.

---

## A. Yang perlu Anda siapkan (sekali)
1. **VPS/Server** dengan Ubuntu 22.04 (RAM minimal 2 GB — agar proses build lancar).
   Contoh penyedia: DigitalOcean, Vultr, Contabo, AWS Lightsail, Biznet Gio, IDCloudHost, DomaiNesia.
2. **Domain** (mis. `biomassa.perusahaan.com`) dan akses ke pengaturan DNS-nya.
3. Aplikasi **Docker** & **Docker Compose** di server (langkah instalasi ada di bawah).

---

## B. Arahkan domain ke server
Di panel DNS domain Anda, buat **A record**:
```
Tipe: A   Nama: biomassa (atau @)   Nilai: <IP_PUBLIK_SERVER>   TTL: default
```
Tunggu propagasi (beberapa menit s/d 1 jam). Uji: `ping biomassa.perusahaan.com` harus mengarah ke IP server.

---

## C. Deploy langkah demi langkah

### 1) Masuk ke server & pasang Docker
```bash
ssh root@IP_SERVER
curl -fsSL https://get.docker.com | sh
```

### 2) Kirim project ke server
Pilih salah satu:
- **Via Git** (jika project sudah di GitHub/GitLab):
  ```bash
  git clone <URL_REPO> gep-app && cd gep-app
  ```
- **Via upload langsung** dari PC Anda (jalankan di PC, folder tanpa spasi lebih mudah — atau kompres dulu):
  ```bash
  scp -r "D:\Cangkang Sawit\aplikasi sumber biomassa PT GEP" root@IP_SERVER:/root/gep-app
  ```
  Lalu di server: `cd /root/gep-app`

  > `node_modules` & `vendor` TIDAK perlu diikutkan (akan dibangun otomatis di dalam Docker).

### 3) Buat file konfigurasi produksi
```bash
cp .env.production.example .env.production
nano .env.production
```
Isi minimal:
- `APP_KEY=` → buat dengan: `echo "base64:$(openssl rand -base64 32)"` lalu tempel hasilnya.
- `APP_URL=https://biomassa.perusahaan.com`
- `DOMAIN=biomassa.perusahaan.com`
- `ADMIN_EMAIL=email-anda@perusahaan.com` (untuk notifikasi Let's Encrypt)
- `DB_PASSWORD=` dan `DB_ROOT_PASSWORD=` → password kuat & acak.
- `SEED_ON_DEPLOY=true` (biarkan true untuk deploy pertama).

Simpan (Ctrl+O, Enter, Ctrl+X).

### 4) Jalankan!
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```
Proses build pertama 3–8 menit (mengunduh image, build React, install PHP). Pantau log:
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f app
```
Tunggu sampai muncul `==> Siap. Menjalankan web server.`

### 5) Buka aplikasi
Kunjungi **https://biomassa.perusahaan.com** dari perangkat mana saja.
Login:
```
Email    : admin@pt-gep.com
Password : Admin@12345
```

### 6) 🔒 WAJIB setelah login pertama
- **Ganti password admin** (via database untuk saat ini, karena menu ganti password belum ada):
  ```bash
  docker compose --env-file .env.production -f docker-compose.prod.yml exec app \
    php artisan tinker --execute="\$u=App\Models\User::where('email','admin@pt-gep.com')->first(); \$u->password=bcrypt('PasswordBaruAnda!'); \$u->save(); echo 'ok';"
  ```
- Setelah data awal masuk, ubah `SEED_ON_DEPLOY=false` di `.env.production` lalu deploy ulang (langkah update) agar tidak seeding lagi.

---

## D. Update aplikasi (rilis baru)
```bash
cd /root/gep-app
git pull                      # atau upload ulang file
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```
Data di database tetap aman (tersimpan di volume `dbdata`).

---

## E. Backup & restore database
**Backup:**
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec db \
  sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" gep_erp' > backup_$(date +%F).sql
```
**Restore:**
```bash
cat backup_2026-07-25.sql | docker compose --env-file .env.production -f docker-compose.prod.yml exec -T db \
  sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" gep_erp'
```

---

## F. Keamanan (checklist)
- [ ] Password admin sudah diganti.
- [ ] `APP_DEBUG=false` (sudah default di compose).
- [ ] `DB_PASSWORD` & `DB_ROOT_PASSWORD` kuat dan rahasia.
- [ ] File `.env.production` tidak di-commit ke git / tidak dibagikan.
- [ ] Firewall server hanya buka port 22 (SSH), 80, 443:
  ```bash
  ufw allow 22 && ufw allow 80 && ufw allow 443 && ufw enable
  ```

---

## G. Tanpa domain (akses via IP saja, tanpa HTTPS)
Untuk uji cepat tanpa domain:
1. Di `docker-compose.prod.yml`, HAPUS/komentari seluruh service `caddy`, dan AKTIFKAN blok `ports` di service `app`.
2. Set `APP_URL=http://IP_SERVER:8000`.
3. `docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build`
4. Buka `http://IP_SERVER:8000`.
> Tidak disarankan untuk produksi (tidak terenkripsi).

---

## H. Alternatif: Platform instan (tanpa kelola server)
Jika tak ingin mengurus VPS, layanan berbasis Dockerfile ini juga bisa dipakai di:
- **Railway** (railway.app), **Render** (render.com), **Fly.io** — hubungkan repo, mereka build `Dockerfile` otomatis, sediakan MySQL terkelola, dan beri URL HTTPS.
- Set environment variables yang sama seperti `.env.production` (APP_KEY, APP_URL, DB_*), dan arahkan port aplikasi ke **80**.
- Untuk platform ini, service `caddy` tidak dipakai (HTTPS disediakan platform).

---

## I. Masalah umum
| Gejala | Solusi |
|--------|--------|
| Build gagal "out of memory" | VPS < 2GB. Upgrade RAM, atau build image di PC lalu `docker push`/`docker save` ke server. |
| Halaman putih / 500 | `docker compose ... logs -f app`. Pastikan `APP_KEY` terisi. |
| SSL gagal terbit | Pastikan A record domain sudah mengarah ke IP server & port 80/443 terbuka. |
| Aset (CSS/JS) tidak muncul | Pastikan `APP_URL` = domain HTTPS yang benar, lalu deploy ulang. |
| Perlu re-seed dari nol | `docker compose ... down -v` (HATI-HATI: hapus semua data) lalu `up` lagi. |

---

**Kredensial demo:** `admin@pt-gep.com` / `Admin@12345` (ganti setelah deploy).
**Dibuat untuk:** PT Geosys Energi Prima.
