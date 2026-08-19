---
title: Integrasi & Deploy Railway (Sumber Biomassa & Manajemen Task)
aliases: [Railway Deployment, Integrasi Railway]
tags: [teknis, alur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[teknis-moc]]"]
---
# Integrasi & Deploy Railway (Sumber Biomassa & Manajemen Task)

Rencana menghubungkan aplikasi PT GEP ERP ini dengan dua proyek yang sudah berjalan di platform **Railway**: **"Sumber Biomassa"** dan **"Manajemen Task"**.

> [!danger] Batasan keras dari user (WAJIB dipatuhi)
> Data yang sudah diinput/beroperasi di kedua proyek tersebut **tidak boleh terhapus atau hilang sedikit pun** dan **tidak boleh terganggu**. Instruksi eksplisit: **"hanya pindahkan datanya"** — bukan mengganti, menimpa, atau menjalankan migrasi destruktif di proyek sumber.

## Status
**Selesai dieksekusi (2026-08-16).** Data dari kedua service berhasil disalin ke database MySQL aplikasi PT GEP ERP ini. Proyek sumber di Railway **tidak diubah sama sekali** (hanya operasi baca: `ls`, `wc -c`, `base64 <file` via SSH — nol tulis/hapus).

## Temuan pemetaan Railway
- Akun Railway `ralieharahap-ui` hanya punya **1 proyek**: `abundant-abundance` (workspace "ralieah's Projects"), berisi **2 service** dari repo yang sama (`ralieharahap-ui/work`):
  - **`work`** (ID `223ea4f8-bb3b-490c-b942-46dc3b42d666`) = **Sumber Biomassa** (dikonfirmasi user).
  - **`gep-task-management`** (ID `1747674f-95b8-48d5-a982-e8406182fe07`) = **Manajemen Task** (dikonfirmasi user).
- Keduanya Laravel app dengan **volume Railway di `/data`** (pola khas SQLite file-based, bukan Postgres/MySQL plugin terpisah — tidak ada service database lain di proyek ini).
- Koneksi MCP Railway di sesi ini via OAuth → **nilai environment variable ter-redacted** (hanya nama variabel yang terlihat, bukan isinya) — tidak ada jalur baca langsung ke `DB_CONNECTION`/`DB_DATABASE` aktual atau kredensial dari sisi Claude.

## Cara pemindahan data yang akhirnya dipakai
User mengizinkan Claude mengambil sendiri via **Railway CLI + SSH** (bukan manual export oleh user seperti rencana awal). Langkah teknis:
1. `railway link -p <project-id> -e production`.
2. `railway ssh keys add` — daftarkan public key lokal (`~/.ssh/id_ed25519.pub`, key existing bernama "runpod") ke akun Railway agar bisa SSH.
3. `railway ssh config --service <name> --dry-run` — ambil `HostName`/`User` gateway SSH per service (`ssh.railway.com`, user = UUID deployment).
4. Karena `railway ssh` bawaan gagal non-interaktif (`Host key verification failed` — CLI-nya butuh TTY untuk konfirmasi host key pertama kali, tidak tersedia di lingkungan otomatis), dipakai **OpenSSH langsung** (`ssh -o StrictHostKeyChecking=accept-new -i ~/.ssh/id_ed25519 <user>@ssh.railway.com "..."`) dengan host `ssh.railway.com` yang resmi/terdokumentasi — bukan host asing.
5. Isi file diambil murni via **pembacaan**: `ls`, `wc -c`, `base64 /data/database.sqlite` — tidak ada perintah tulis/hapus/restart apa pun ke container sumber.
6. Ukuran file hasil salin diverifikasi identik byte-per-byte dengan `wc -c` di sisi server sebelum dipakai.

## Temuan skema & data riil
Kedua service memakai skema SQLite yang sama dengan aplikasi PT GEP ini (dibangun dari playbook `laravel-inertia-map-app` yang sama):
- **Identik dengan seed yang sudah ada di PT GEP ERP** (dilewati, tidak diimpor ulang agar tidak dobel): `divisions` (10 baris), `pawm_pltus` (8 PLTU).
- **Data riil baru, tidak ada overlap** → diimpor: `palm_oil_sources` (33 dari Sumber Biomassa), `unloading_points` (53 dari Sumber Biomassa), `calculation_scenarios` (2 dari Sumber Biomassa).
- **Modul baru** (tabel belum ada sebelumnya di PT GEP ERP, dibuatkan migrasi baru non-destruktif — lihat migrasi `2026_08_16_000001_create_task_management_tables.php` & model `TaskProject`/`Task`/`TaskChecklistItem`/`TaskComment`): `task_projects` (3), `tasks` (26), `task_checklist_items` (28), `task_comments` (0). **UI/menu untuk modul ini belum dibuat** — data baru tersimpan aman, menyusul kalau diminta.
- **User**: 15 user di kedua sumber (5 + 10, ada overlap 1 alamat email lintas sumber + `admin@pt-gep.com` yang sudah ada di keduanya dan di target) → **12 akun login baru dibuat** di PT GEP ERP dengan role `external` (paling minim), password hash asli dipakai ulang (bisa login pakai password lama). Akun `admin@pt-gep.com` yang sudah ada di target **sama sekali tidak disentuh/ditimpa** — baris & ID-nya persis sama sebelum-sesudah.
- Kolom `organization_id` semua baris yang diimpor dipetakan ke organization PT GEP ERP yang sudah ada (bukan ID organization dari database sumber). Kolom `division_id`/`pic_id`/`created_by`/dst dipetakan lewat kode divisi & email user agar foreign key tetap valid (diverifikasi 0 baris orphan setelah impor).
- Tabel `roles`/`permissions`/`model_has_roles` dari sumber **tidak diimpor sama sekali** — role/permission PT GEP ERP (khusus akuntansi: super_admin/drafter/reviewer/approval/external) tidak boleh tercampur dengan vocabulary role app lain.
- Seluruh proses impor dibungkus satu `DB::transaction()` — percobaan pertama gagal karena kolom `text` checklist kepanjangan (`varchar(255)`), transaksi otomatis rollback tanpa data korup, kolom diperbaiki jadi `TEXT`, dijalankan ulang → sukses penuh.

## Verifikasi akhir (setelah impor)
| Tabel | Sebelum | Sesudah |
|---|---|---|
| palm_oil_sources | 10 | 43 |
| unloading_points | 3 | 56 |
| calculation_scenarios | 1 | 3 |
| users | 1 | 13 |
| task_projects / tasks / task_checklist_items | 0 / 0 / 0 | 3 / 26 / 28 |
| divisions, pawm_pltus, jetty_points | tidak berubah (dilewati / tidak ada data baru) | sama |

0 foreign key orphan. Akun `admin@pt-gep.com` (id & data) identik sebelum-sesudah. File SQLite hasil salin di temp lokal **sudah dihapus** setelah data terverifikasi tersimpan di MySQL (mengandung hash password, tidak perlu tersimpan lebih lama dari perlu).

## Catatan tindak lanjut
- ✅ **Selesai**: menu/UI **Manajemen Tugas** sudah dibangun di PT GEP ERP — lihat [[manajemen-task-data]].
- SSH key "runpod" **masih terdaftar** di akun Railway (didaftarkan khusus untuk ekspor ini) — bisa dicabut lewat `railway ssh keys` kalau tidak diperlukan lagi untuk akses SSH ke Railway ke depannya.
- 12 user baru berrole `external` (akses minim) — user PT GEP disarankan meninjau & menyesuaikan role masing-masing sesuai kebutuhan riil mereka di aplikasi ini.
