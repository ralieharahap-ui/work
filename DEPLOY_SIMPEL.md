# 🚀 Deploy Paling Sederhana — Railway (tanpa kelola server, tanpa MySQL terpisah)

Aplikasi ini **tidak bisa** di-drag&drop ke Netlify (Netlify hanya untuk situs statis; aplikasi ini butuh PHP + database).
Cara termudah yang **benar-benar bekerja** adalah **Railway** — mirip Netlify tapi untuk aplikasi full-stack:
Anda hubungkan sekali, Railway otomatis membangun (pakai `Dockerfile` yang sudah ada) dan memberi URL HTTPS.

Kita pakai **SQLite** (database = 1 file) supaya **tidak perlu setup database sama sekali**.

> Perkiraan biaya: Railway memberi kredit uji coba; setelah itu ± $5/bulan (pemakaian kecil). Tidak ada yang benar-benar gratis-selamanya untuk aplikasi PHP+DB.

---

## Langkah (± 10 menit)

### 1) Taruh kode di GitHub
Cara termudah agar Railway bisa membangunnya. (Saya bisa bantu siapkan repo-nya — tinggal minta.)
Atau lewat CLI tanpa GitHub — lihat bagian bawah.

### 2) Buat APP_KEY (sekali, penting)
Di komputer mana saja yang ada PHP, atau lewat situs, buat nilai acak. Contoh via Git Bash / terminal:
```bash
echo "base64:$(openssl rand -base64 32)"
```
Salin hasilnya (mis. `base64:AbCd...==`). Simpan untuk langkah 5.

### 3) Deploy di Railway
1. Buka **railway.com** → **Login** (pakai akun GitHub).
2. **New Project** → **Deploy from GitHub repo** → pilih repo aplikasi ini.
3. Railway mendeteksi `Dockerfile` dan mulai membangun otomatis (biarkan berjalan).

### 4) Tambah Volume (agar data tidak hilang saat update)
Di service aplikasi → tab **Variables/Settings** → **Volumes** → **New Volume**
- Mount path: `/var/www/html/database`

### 5) Isi Variables (Environment)
Di tab **Variables**, tambahkan:
| Nama | Nilai |
|------|-------|
| `APP_KEY` | *(hasil langkah 2, mis. `base64:AbCd...==`)* |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `/var/www/html/database/database.sqlite` |
| `SEED_ON_DEPLOY` | `true` |

### 6) Aktifkan domain
Tab **Settings → Networking → Generate Domain**. Anda dapat URL seperti `https://gep-xxxx.up.railway.app`.
Lalu tambah 1 variable lagi:
| `APP_URL` | *(URL dari Railway tadi)* |

Railway otomatis deploy ulang.

### 7) Buka & login
Buka URL Railway dari HP/laptop mana saja:
```
Email    : admin@pt-gep.com
Password : Admin@12345
```
**Ganti password admin** (via tab Railway → service → **Shell**/**Command**):
```bash
php artisan tinker --execute="$u=App\Models\User::where('email','admin@pt-gep.com')->first(); $u->password=bcrypt('PasswordBaruAnda!'); $u->save(); echo 'ok';"
```

Selesai — aplikasi hidup di internet. 🎉

---

## Alternatif tanpa GitHub (Railway CLI)
Jika tak mau pakai GitHub:
```bash
npm i -g @railway/cli
railway login
railway init
railway up            # unggah folder ini & build
```
Lalu lakukan langkah 4–7 di dashboard Railway.

---

## Alternatif lain
- **Render.com** / **Fly.io**: sama-sama membangun `Dockerfile` ini. (Untuk SQLite butuh disk persisten — di Render perlu paket berbayar.)
- **VPS sendiri (kontrol penuh + MySQL)**: lihat **DEPLOY_CLOUD.md**.

---

## Kenapa tidak Netlify / drag & drop?
Netlify, Vercel (static), GitHub Pages = **hanya file statis**. Mereka tidak menjalankan **PHP** dan tidak punya **database**.
Aplikasi ini menyimpan data (sumber, titik bongkar, dermaga, skenario kalkulasi) ke database melalui backend Laravel —
jadi wajib di hosting yang menjalankan PHP + DB seperti Railway/Render/Fly/VPS.

**Catatan:** password admin default harus diganti setelah online.
