# Panduan Lengkap ERP PT Geosys Energi Prima (PT GEP)
### Spesifikasi fungsional + teknis modul Akuntansi, Laporan, Dokumen, dan Manajemen Tugas
> Dokumen ini ditulis agar dapat dipakai ulang sebagai prompt untuk membangun kembali aplikasi yang sama persis. Semua nama tabel, kolom, enum, route, peran, dan aturan bisnis di bawah ini mencerminkan implementasi nyata.

---

## 0. Ringkasan & Stack Teknologi

- **Backend**: Laravel 11 (PHP 8.3), Inertia.js (adapter Laravel).
- **Frontend**: React (JSX) + Inertia + **Tailwind CSS** (`darkMode: 'class'`, tema gelap "Refined Dark"), plugin `@tailwindcss/forms` & `typography`. Font **Inter** (fallback Segoe UI).
- **DB**: MySQL 8 (nama database `gep_erp`). Semua PK **UUID**.
- **Auth & Peran**: `spatie/laravel-permission` (roles + permissions).
- **QR**: `qrcode.react` (`QRCodeSVG`).
- **Multi-tenant**: setiap tabel bisnis punya `organization_id`; seluruh query difilter `organization_id = auth()->user()->organization_id`.
- **Mode aplikasi**: `config('app.mode')` — `full` (default; semua modul) atau `tasks` (hanya Manajemen Tugas; modul biomassa+akuntansi+dokumen disembunyikan di route & sidebar).
- **Pola UI**: shell `AppLayout` (sidebar berkelompok + topbar). Halaman = komponen Inertia di `resources/js/Pages/**`. Kelas komponen bersama di `resources/css/app.css` (`.card`, `.kpi-card`, `.btn-primary/secondary/danger/success/tertiary/ghost`, `.input`, `.badge-*`, `.table-header/cell`, dst).

### Peran (roles) & hierarki
5 role: `super_admin`, `drafter`, `reviewer`, `approval`, `external`.
Permission = `{modul}.{aksi}` untuk modul `['inventory','invoice','billing','books','letters','tasks']` × aksi `['view','create','edit','delete','approve']`.

Matriks permission (seeder `RolePermissionSeeder`):
- **super_admin**: SEMUA permission tiap modul (view/create/edit/delete/approve). Berperan sebagai **Direktur Utama**.
- **drafter**: `view + create + edit` tiap modul.
- **reviewer**: `view + edit` tiap modul.
- **approval**: `view + approve` tiap modul.
- **external**: `view` tiap modul.

### Organisasi, divisi, user awal (seeder `OrganizationSeeder`)
- Organization: `{ slug: 'pt-gep', name: 'PT Geosys Energi Prima' }`.
- Divisi: `Direktur Utama (DIR-UTM)`, `Direktur Operasional (DIR-OPS)` sebagai induk; anak DIR-UTM: `Finance & Tax (FIN)`, `Legal & Compliance (LEG)`, `Sales (SLS)`, `HR (HR)`; anak DIR-OPS: `Procurement (PROC)`, `Operation (OPS)`, `MR (MR)`, `K3 (K3)`.
- User admin demo: **`admin@pt-gep.com` / `Admin@12345`**, hierarchy `administrator`, role `super_admin`.
- Tabel `users`: `id(uuid), name, email, password, organization_id, division_id(nullable), hierarchy(enum: staff|manager|director|stakeholder|administrator), is_active(bool)`. Signup baru berstatus `is_active=false` sampai disetujui super_admin (menu Manajemen User).

---

## 1. MODUL AKUNTANSI (`/books`, permission `books.view`)

Buku besar berbasis **double-entry**. Sumber semua laporan & subledger = jurnal dengan `is_posted = true`.

### 1.1 Chart of Accounts (COA) — `books.accounts.*`
Tabel **`accounts`**:
| kolom | tipe | keterangan |
|---|---|---|
| id | uuid PK | |
| organization_id | uuid FK organizations | |
| code | string | **kode 4-digit** (mis. `1101`) — unik per organisasi |
| name | string | nama akun |
| type | string | `asset` \| `liability` \| `equity` \| `revenue` \| `expense` |
| account_type | string nullable | "Control Account" PSAK — sub-tipe (mis. `Kas di Bank`, `Piutang Usaha`, `Cadangan Kerugian Penurunan Nilai`, `Pajak Dibayar Dimuka`, `Akumulasi Penyusutan`, `Aset Takberwujud`, `Utang Usaha`, `Utang Pajak`, `Utang Pihak Berelasi`, `Ekuitas`, `Beban Pokok Penjualan`, `Beban Usaha`, `Beban Lain-lain`) |
| normal_balance | string(2) nullable | `Db` / `Kr` |
| report | string(3) nullable | `NRC` (Neraca) / `LR` (Laba-Rugi) |
| fs_group | string nullable | **Kelompok FS (PSAK 1)**: Aset Lancar, Aset Tidak Lancar, Liabilitas Jangka Pendek, Liabilitas Jangka Panjang, Ekuitas, Pendapatan, Pendapatan Lain-lain, Beban Pokok Penjualan, Beban Usaha, Beban Lain-lain |
| parent_id | uuid FK accounts nullable | hierarki akun |
| is_active | bool | |

Unique `(organization_id, code)`. CRUD (store/update/destroy) **khusus `super_admin`**; index bisa dilihat semua `books.view`. UI mengelompokkan akun per `fs_group` dengan subtotal & badge Db/Kr.

**Daftar 58 akun bawaan (seeder `ChartOfAccountsSeeder`, idempoten `firstOrCreate` + patch metadata kosong)** — format `[code, name, type, fs_group, account_type, normal_balance(Db/Kr), report(NRC/LR)]`:

Aset Lancar (fs_group "Aset Lancar"): `1101 Kas Kecil/Petty Cash` · `1102 Bank Operasional - Mandiri Giro` · `1103 Bank Tabungan Bisnis` · `1201-1203 Piutang Usaha (Trading/Biomassa/Lumpsum)` · `1209 Cadangan Kerugian Penurunan Nilai Piutang (kontra, Kr)` · `1301 Sewa Dibayar Dimuka` · `1302 Uang Muka Vendor` · `1401-1403 Persediaan` · `1501 PPN Masukan Dapat Dikreditkan` · `1502/1503 PPh 22/23 Dibayar Dimuka`.
Aset Tidak Lancar (fs_group "Aset Tidak Lancar"): `1601 Peralatan Kantor (Aset Tetap)` · `1609 Akumulasi Penyusutan - Peralatan Kantor (kontra, Kr)` · `1701 Lisensi/Aset Takberwujud`.

Liabilitas Jangka Pendek (fs_group "Liabilitas Jangka Pendek"): `2101 Utang Usaha - Vendor` · `2102 Utang Subkontraktor` · `2201 PPN Keluaran (Utang Pajak)` · `2202-2205 PPh 21/22/23/Final 4(2) Terutang (Utang Pajak)` · `2301 Utang Gaji` · `2302 Utang BPJS`.
Liabilitas Jangka Panjang (fs_group "Liabilitas Jangka Panjang"): `2401 Utang Pihak Berelasi - Direksi`.

Ekuitas (3xxx, Kr kecuali dividen): `3101 Modal Disetor` · `3201 Saldo Laba` · `3301 Dividen (Db)`.

Pendapatan (4xxx, Kr, LR): `4101 Pendapatan Trading` · `4102 Pendapatan Biomassa` · `4103 Pendapatan Proyek Lumpsum` · `4201 Pendapatan Bunga Bank (Pendapatan Lain)`.

Beban Pokok Penjualan (5xxx, Db, LR, fs_group "Beban Pokok Penjualan"): `5101 Beban Pokok Penjualan - Trading` · `5201 Beban Pokok Penjualan - Biomassa` · `5202 Transport Biomassa` · `5203 Handling/Bongkar Muat Biomassa` · `5204 QC & Sampling Biomassa` · `5205 Administrasi Biomassa/Direct Project Expense` · `5206 Tenaga Ahli Biomassa` · `5207 Material Proyek Lumpsum` · `5208 Tenaga Ahli Proyek Lumpsum` · `5209 Administrasi Proyek Lumpsum` · `5299 Penalti/Klaim Kontrak`.

Beban/OPEX (6xxx, Db, LR): `6101 Gaji & THR` · `6102 BPJS & Benefit` · `6103 ATK & Operasional Kantor` · `6104 Sewa & Utilitas` · `6105 Legal & Konsultan` · `6106 Beban Administrasi & Bank` · `6107 Rapat & Perjalanan Dinas Kantor` · `6108 Reimbursement Kantor` · `6110 Membership & Subscription` · `6111 Sertifikasi & Perizinan` · `6112 Beban Penyusutan` · `6201 Beban Pajak/Bunga Bank (Beban Lain)`.

> Akun legacy format lama `X-XXXX` yang belum dipakai jurnal dinonaktifkan (`is_active=false`, reversible); yang sudah dipakai tetap aktif agar jurnal historis valid.

### 1.2 Jurnal Umum — `books.journal.*`
Tabel **`journal_entries`**: `id, organization_id, entry_no (JE-YYYYMM-NNNN), entry_date, description, ref_type(nullable), ref_id(uuid nullable), is_posted(bool), status(draft|pending|posted|rejected), current_level(tinyint 0), created_by(uuid users), reject_reason(nullable), timestamps`. Unique `(organization_id, entry_no)`.
Tabel **`journal_lines`** (tanpa timestamps): `id, journal_entry_id, account_id, aux_code(nullable), debit(decimal 18,2), credit(decimal 18,2), memo(nullable)`.
Tabel **`journal_approvals`**: `id, journal_entry_id, level(tinyint), user_id, action(submitted|approved|rejected), notes, created_at`.
Tabel **`journal_attachments`**: `id, journal_entry_id, path, original_name, mime, size` (disk `public`, folder `journal-docs/{entry_id}`).

**Aturan pembuatan**: minimal 2 baris; **total debet = total kredit** dan **> 0** (kalau tidak, ditolak). Baris ber-nilai 0/0 diabaikan. Nomor `JE-` + `YYYYMM` + urut 4 digit (hitung per prefix).

**Alur approval bertingkat (maker-checker)**:
- `approveLevelFor(user)`: super_admin → **2** (Direktur Utama); reviewer/approval → **1**; lainnya → 0.
- Ambang **`DIRECTOR_THRESHOLD = 50.000.000`**.
- State machine:
  1. `draft` (baru dibuat) → drafter/pembuat/super_admin bisa edit (juga saat `rejected`).
  2. **submit** → `pending`, `current_level=1`, tercatat approval `submitted`.
  3. **approve L1** (reviewer/approval/super_admin) → jika total > 50 juta → naik ke `current_level=2` tetap `pending` (butuh Direktur); jika ≤ 50 juta → langsung `posted` + `is_posted=true`, `current_level=0`.
  4. **approve L2** (hanya super_admin) → `posted` + `is_posted=true`.
  5. **reject** (dengan alasan wajib) → `rejected`, kembali ke pembuat, `reject_reason` terisi.
- `canEdit`: status ∈ {draft, rejected} DAN (super_admin ATAU pembuat).
- `canApprove`: status = pending DAN (super_admin ATAU (approveLevel≥1 DAN current_level=1)).
- **Posting = jurnal masuk buku besar/laporan** (hanya `is_posted=true` yang dihitung).
- Hapus jurnal: super_admin atau pembuat (hapus attachment fisik + approvals + lines).

**Subledger via `aux_code`**: baris jurnal boleh diberi kode bantu = kode Vendor atau kode Customer. Saldo utang/piutang dihitung on-the-fly dari `journal_lines` yang `aux_code` cocok pada jurnal posted.

**Automasi Tahap 2 — Recommender kode akun** (`App\Services\AccountRecommender`): peta kata-kunci → kode akun (aturan `rules()` dikirim ke halaman). Saat mengetik keterangan/memo, muncul chip saran akun; klik mengisi baris jurnal.

### 1.3 Buku Besar — `books.ledger.index`
Read-only. Menampilkan mutasi & saldo berjalan per akun per tahun, dari jurnal posted. Edit dilakukan lewat Jurnal Umum.

### 1.4 Aset Tetap & Penyusutan — `books.fixed-assets.*`
Tabel **`fixed_assets`**: `id, organization_id, description, purchase_date, qty(int, default 1), unit_cost(decimal 18,2), residual_value(decimal 18,2), useful_life_months(int), account_id(nullable FK accounts), notes, timestamps`.
Perhitungan **garis lurus, on-the-fly** (tidak disimpan): `totalCost = unit_cost×qty`; `depreciableBase = totalCost − residual_value`; `monthlyDepreciation = depreciableBase / useful_life_months`; bulan pembelian = bulan pertama penyusutan; `accumulated(asOf)`, `bookValue(asOf) = totalCost − accumulated`.
CRUD khusus `super_admin`.
**Automasi Tahap 5-6 — Auto-jurnal penyusutan** (`postDepreciation`, `books.fixed-assets.depreciate`, khusus super_admin): pilih periode `YYYY-MM` → buat **satu jurnal posted** `D 6112 Beban Penyusutan | K 1609 Akumulasi Penyusutan` sebesar total penyusutan aset yang masih berjalan pada periode itu. Guard `ref_type='depreciation'` + `ref_id=YYYY-MM` mencegah posting ganda. Butuh akun `6112` & `1609` ada.

### 1.5 Master Vendor — `books.vendors.*`
Tabel **`vendors`**: `id, organization_id, code, name, phone, address, payable_balance(decimal 18,2), is_active, timestamps`. Unique `(organization_id, code)`. CRUD khusus super_admin; index+show untuk books.view. Halaman menampilkan **subledger utang**: `saldo = payable_balance + Σ(credit−debit)` dari journal_lines liability dengan `aux_code = vendor.code` (jurnal posted).

### 1.6 Master Customer — `books.customers.*`
"Customer" memakai ulang model **`UnloadingPoint`** (titik bongkar) dengan kolom tambahan `code` + `receivable_balance` (migrasi `add_aux_to_unloading_points`). Subledger **piutang** dihitung dari journal_lines yang `account.account_type LIKE 'Piutang%'` dan `aux_code = customer.code`. `update` khusus super_admin.

---

## 2. MODUL LAPORAN (`/books`, permission `books.view`) — `ReportController`

Semua laporan hanya membaca jurnal `is_posted=true`.

### 2.1 Dashboard Manajemen — `books.dashboard` (Tahap 6)
Ringkasan YTD per tahun: Pendapatan usaha (`4101-4103`), Pendapatan lain (`4201`), HPP (`5xxx`), **Laba Kotor + margin %**, OPEX (`6101-6112`), Beban lain (`6201`), **Laba Bersih**; posisi ringkas (Kas `1101-1103`, Piutang `1201-1203`, Utang `2101-2102`); **margin per segmen** (Trading `4101`vs`5101`; Biomassa `4102`vs`5201-5206`; Lumpsum `4103`vs`5207-5209,5299`).

### 2.2 Neraca Saldo (Trial Balance) — `books.trial-balance`
Saldo debet/kredit per akun s/d tanggal.

### 2.3 Neraca Lajur (Worksheet) — `books.worksheet`
Neraca saldo → dipisah ke kolom LR vs NRC berdasar `report`/`type`. Laba berjalan = ΣLR_kredit − ΣLR_debet.

### 2.4 Neraca (Balance Sheet) — `books.balance-sheet`
Aktiva (type asset, saldo debet) vs Kewajiban+Modal (saldo kredit) + laba tahun berjalan.

### 2.5 Laba/Rugi bertingkat — `books.profit-loss`
Format multi-step: **Pendapatan − Biaya Langsung (Beban Pokok Penjualan) = Laba Kotor (+margin%)** lalu **− Biaya Tetap (Beban Usaha) = Laba Bersih**. Klasifikasi biaya: `fs_group` diawali `"Beban Pokok"` → Direct Cost (termasuk `5299`; `"COGS"` juga didukung untuk kompatibilitas lama); selain itu → Biaya Tetap. Fallback akun legacy tanpa fs_group: kode 4-digit murni `5xxx` → Direct, `5-xxxx` legacy → Fixed.

### 2.6 Peredaran Bruto — `books.gross-turnover`
Peredaran bruto per bulan × **PPh Final UMKM 0,5%** (`rate = 0.005`), dari akun revenue posted.

### 2.7 Kontrol Pajak — `books.tax-control` (Tahap 4)
Rekonsiliasi per bulan: PPN Masukan `1501` vs PPN Keluaran `2201` → kurang/(lebih) bayar; PPh terutang `2202-2205` & PPh dibayar dimuka `1502/1503`.

### 2.8 Audit AI — `books.audit` (Tahap 8, `AuditController` + `App\Services\AnomalyDetector`)
Antrean pengecualian jurnal per tahun, urut severity:
- **high**: jurnal tidak seimbang (debet ≠ kredit).
- **medium**: akun nonaktif dipakai; kemungkinan duplikat (tanggal+total nilai identik pada >1 jurnal).
- **low**: nominal nol (posted tapi total 0); tanpa dokumen pendukung (posted, nilai>0, tanpa attachment).
- **Margin negatif** per bulan: pendapatan `4xxx` < HPP `5xxx`.
UI: 4 kartu ringkasan berfilter (Total/Tinggi/Sedang/Rendah) + daftar pengecualian + tabel margin negatif.

---

## 3. MODUL DOKUMEN (`/documents`, permission `letters.view`) — `DocumentController`

Generator 11 jenis dokumen resmi dengan kop PT GEP, penomoran otomatis, status, tanda tangan, QR verifikasi, dan auto-jurnal.

### 3.1 Tabel `documents`
`id, organization_id, user_id(pembuat), type, number, doc_date, meta(json), ref_type(nullable), ref_id(nullable), notes, status, released_by, released_at, timestamps`. Unique `(organization_id, number)`. (Kolom `status/released_by/released_at` dari migrasi `add_status_to_documents`.)
`meta` menyimpan seluruh field spesifik per jenis: umumnya `{ party:{name,instansi,address,...}, items:[{name,qty,unit,keterangan}], amounts:{subtotal,tax,total}, tembusan:[], extra:{...} }`.

### 3.2 11 jenis dokumen (registry `types()`)
`invoice (INV, sumber scenario)` · `faktur (FKT, scenario)` · `kwitansi (KW, scenario)` · `surat_jalan (SJ, shipment)` · `voucher_jurnal (VJ, journal)` · `tanda_terima (TT)` · `perjalanan_dinas (PD)` · `reimbursement (RB)` · `po (PO, vendor)` · `do (DO, shipment)` · `surat_resmi (SR)`. Field `source` menentukan data prefill (scenario dari `CalculationScenario`, journal dari jurnal posted, shipment dari PalmOilSource+UnloadingPoint, vendor dari Vendor).

### 3.3 Penomoran
Format **`[urut 3 digit]/GEP/[prefix]/[bulan romawi]/[tahun]`** — contoh `001/GEP/DO/VIII/2026`. Urut = max nomor jenis (prefix) tahun berjalan + 1 (tahan terhadap penghapusan).

### 3.4 Workflow status & akses
Status: `on_review → signed → released`, atau `cancelled`. Label: On Review / Signed / Released / Cancelled.
- **create/store**: `letters.create` (drafter/super_admin). Dokumen baru `status=on_review`.
- **edit/update (revisi)**: **khusus `super_admin` & `reviewer`** (route `role:super_admin|reviewer`). Form yang sama dengan create, ter-prefill.
- **Kunci dokumen**: bila status `signed`/`released` → **terkunci**; hanya **super_admin** yang boleh merevisi (reviewer diblok 403). `isLocked = status ∈ {signed, released}`. `can_edit = super_admin || (reviewer && !isLocked)`.
- **setStatus** (Signed/Released/Cancelled/On Review): **khusus super_admin** (route `role:super_admin`).
- **destroy**: `letters.delete`.

### 3.5 Auto-jurnal saat rilis
Saat status → `released`, dokumen `perjalanan_dinas`/`reimbursement` **membuat jurnal posted otomatis**: `D meta.extra.debit_account_id | K meta.extra.credit_account_id` sebesar `meta.amounts.total`. Guard `meta.extra.posted_journal_id` cegah dobel; `ref_type='document'`, `ref_id=document.id`.

### 3.6 QR & Verifikasi Publik
- Dokumen terkunci (signed/released) menampilkan **QR di kanan atas** (di bawah tanggal; untuk surat_resmi di bawah badge INTERNAL/EKSTERNAL), keterangan kecil "Pindai untuk verifikasi".
- QR mengenkode URL **`route('documents.verify', id)`**.
- **Endpoint publik (tanpa login)**: `GET /verify/{id}` → `DocumentController@verify` → view `resources/views/verify.blade.php`. Menampilkan status keaslian (Nomor, Jenis, Tanggal [locale id], Status, Kode `GEP-`+10 char UUID) HANYA untuk dokumen signed/released; selain itu "Dokumen Tidak Terverifikasi". Tidak mengekspos nilai/isi. Halaman `noindex`.
- Footer dokumen: `Website | Halaman 1 | Kode Dokumen: GEP-XXXX` + nota `Mengetahui/Menyetujui: Direksi PT Geosys Energi Prima` (dipindah dari area TTD ke footer, ukuran font footer).

### 3.7 Blok tanda tangan
- `surat_jalan`: 3 kolom (Perwakilan Driver / Penerima / PT Geosys K3).
- `kwitansi, voucher_jurnal, perjalanan_dinas, reimbursement, do`: komponen `PersonnelSign` — kolom kanan "Hormat Kami / signer". Bila dirilis Direksi → penandatangan Direksi.
- **`tanda_terima`**: kolom kiri **"Diterima oleh"** dengan 4 baris kosong diisi manual — **Instansi, Tanda tangan, Nama, Jabatan**; kolom kanan "Hormat Kami" (PT GEP).
- lainnya: `DirekturSign` (Hormat Kami / PT Geosys / Nama / Jabatan).

### 3.8 Dokumentasi (log) — `documents.log`
Rekaman seluruh dokumen: nomor | tanggal | jenis | perihal | pembuat | perilis | status; filter tipe/status/tahun.

---

## 4. MODUL MANAJEMEN TUGAS (`/tasks`, permission `tasks.view`) — `TaskController`, `TaskProjectController`, `TaskCommentController`

Papan kanban ala Notion, per organisasi.

### 4.1 Tabel
- **`task_projects`**: `id, organization_id, owner_id(nullable users), title, description, status(Planning|Ongoing|On Hold|Closed), end_date(nullable), timestamps`.
- **`tasks`**: `id, organization_id, project_id(nullable), division_id(nullable), pic_id(nullable users), created_by(nullable users), title, description, category, status, priority, deadline(nullable), evidence_path(nullable), evidence_original_name(nullable), closed_by(nullable), closed_at(nullable), timestamps`.
- **`task_checklist_items`**: `id, task_id, text, is_completed(bool), position(int), timestamps`.
- **`task_comments`**: `id, task_id, user_id(nullable), body, timestamps`.

### 4.2 Enum & aturan
- `category`: `Operasional | Strategis`.
- `status`: `To Do | In Progress | Review | Blocked | Done`.
- `priority`: `Low | Medium | High | Urgent`.
- **Menutup task (→ Done) WAJIB lampirkan dokumen bukti (evidence)** (`close`, file pdf/img/doc/xls ≤10MB) — set `closed_by`, `closed_at`. Kanban `updateStatus` tidak boleh langsung ke Done (harus via `close`).
- Membuka kembali task Done → bersihkan `closed_by/closed_at`.
- Checklist (subtasks) disinkron ulang tiap simpan (delete+recreate, urut `position`).
- Komentar: siapa pun dengan `tasks.view` boleh menulis.
- **Akses**: create/edit butuh `tasks.create` atau `tasks.edit`; delete butuh `tasks.delete`; kelola project butuh role `super_admin|approval|reviewer`.
- **Alerts** (`App\Services\TaskAlertService`): notifikasi tugas (mis. mendekati/melewati deadline) → badge merah di sidebar `Manajemen Tugas`.

### 4.3 UI
Halaman `Tasks/Index` dengan tampilan: Dashboard, Kanban (drag antar status), Projects, Team. Modal task (detail, checklist, komentar, evidence). Data dikirim: tasks, projects, team (user+role+divisi), divisions, roles, alerts, flag akses.

---

## 5. ROUTE & PERMISSION (ringkas)

Publik: `POST /login`, `GET/POST /register`, `POST /logout`, **`GET /verify/{id}`** (verifikasi dokumen, tanpa login).

Terproteksi `['auth','active']`, dan modul biomassa+akuntansi+dokumen hanya aktif saat `app.mode != 'tasks'`:
- `/` Dashboard peta (biomassa) · `/palm-oil-sources` · `/unloading-points` · `/jetty-points` · `/project-calculator` (`inventory.*`).
- **`/books`** (`books.view`): `dashboard` · `accounts.index` (+store/update/destroy super_admin) · `journal.index` (+store/update/submit/destroy `books.create`; approve/reject `role:reviewer|approval|super_admin`; attachment.destroy) · `vendors.*` (mutasi super_admin) · `customers.*` (update super_admin) · `ledger.index` · `fixed-assets.*` (+depreciate, mutasi super_admin) · `trial-balance` · `worksheet` · `balance-sheet` · `profit-loss` · `gross-turnover` · `tax-control` · `audit`.
- **`/documents`** (`letters.view`): index · log · create/store (`letters.create`) · show · **edit/update (`role:super_admin|reviewer`)** · status (`role:super_admin`) · destroy (`letters.delete`).
- `/admin/users` (`role:super_admin`): kelola & aktivasi user.
- **`/tasks`** (`tasks.view`): index · store/update/updateStatus/close/destroy · comments · task-projects store/update/destroy. (Aktif di semua mode.)

---

## 6. DATA SEED & MENJALANKAN

Urutan `DatabaseSeeder`: `RolePermissionSeeder` → `OrganizationSeeder` → (mode full) `PawmPLTUSeeder, PalmOilSourceSeeder, UnloadingPointSeeder, JettyPointSeeder, ChartOfAccountsSeeder` → `TaskDemoSeeder`.

Perintah:
```bash
php artisan migrate --force
php artisan db:seed --force
npm run build          # atau: npm run dev (Vite HMR)
php artisan serve --host=127.0.0.1 --port=8461
```
Login demo: **admin@pt-gep.com / Admin@12345**. (`.env`: `DB_CONNECTION=mysql`, `DB_DATABASE=gep_erp`, `APP_MODE=full`.)

Fitur upload file (jurnal attachment, task evidence) disimpan di disk `public` → jalankan `php artisan storage:link`.

---

## 7. INVARIAN / ATURAN BISNIS PENTING (jangan dilanggar)

1. Semua query difilter `organization_id` (multi-tenant); akses lintas-organisasi → 403.
2. Jurnal harus seimbang (Σdebet = Σkredit > 0) untuk disimpan/diperbarui.
3. Hanya jurnal `is_posted=true` yang masuk buku besar, laporan, dan subledger.
4. Posting jurnal terjadi **otomatis** saat approval selesai penuh (bukan aksi terpisah).
5. Approval L2 (Direktur/super_admin) wajib bila total jurnal > Rp50.000.000.
6. Dokumen `signed`/`released` terkunci; revisi hanya oleh super_admin.
7. Auto-jurnal (penyusutan, dokumen rilis) idempoten via `ref_type`+`ref_id` / `posted_journal_id`.
8. Menutup task wajib evidence.
9. Migrasi memakai guard `Schema::hasTable/hasColumn` → aman dijalankan ulang (idempoten), tidak menimpa data.
10. Perubahan presentasi (redesign/template) tidak mengubah logika, API, atau skema data.

---
*Dokumen referensi internal PT GEP — mencerminkan implementasi pada branch `main` (per Agustus 2026).*
