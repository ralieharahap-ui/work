# 🤖 Asisten AI — AI Personal Office Employee

Modul `/agent` adalah pegawai kantor digital: ia menerima pekerjaan dengan bahasa
biasa, **merencanakan**, **mengerjakan lewat tool**, **memeriksa hasilnya**, lalu
**mengubah setiap pekerjaan menjadi pengalaman** yang membuat pekerjaan berikutnya
lebih cepat dan lebih jarang salah.

Interaksinya bisa lewat dasbor web maupun **chat Telegram**.

---

## A. Ringkasan Arsitektur

```
                    ┌──────────────────────┐
   web · telegram   │      PEKERJAAN       │  AgentTask (state lengkap, tersimpan)
   cli  · penjadwal └──────────┬───────────┘
                               ↓
                    ┌──────────────────────┐
                    │  PEMAHAMAN TUGAS     │  Planner::understand()  → jenis, risiko, hasil
                    └──────────┬───────────┘
                               ↓
                    ┌──────────────────────┐
                    │ PENGAMBILAN MEMORI   │◄─────────┐  MemoryManager::recall()
                    └──────────┬───────────┘          │
                               ↓                      │
                    ┌──────────────────────┐          │
                    │      PERENCANA       │          │  Planner (prosedur → pengalaman → baru)
                    └──────────┬───────────┘          │
                               ↓                      │
                    ┌──────────────────────┐          │
                    │  KEBIJAKAN & IZIN    │          │  PolicyEngine + ApprovalService
                    └──────────┬───────────┘          │
                               ↓                      │
                    ┌──────────────────────┐          │
                    │      EKSEKUTOR       │          │  Executor (+ idempotensi)
                    └──────────┬───────────┘          │
                               ↓                      │
                    ┌──────────────────────┐          │
                    │        TOOLS         │          │  13 tool di balik satu kontrak
                    └──────────┬───────────┘          │
                               ↓                      │
                    ┌──────────────────────┐          │
                    │     PEMERIKSAAN      │          │  Verifier (berbasis bukti)
                    └───────┬───────┬──────┘          │
                       GAGAL│       │BERHASIL         │
                            ↓       ↓                 │
                   ┌────────────┐ ┌──────────────┐    │
                   │ PEMULIHAN  │ │   REFLEKSI   │    │  RecoveryPlanner / Reflector
                   │ & REVISI   │ └──────┬───────┘    │
                   └────────────┘        ↓            │
                                ┌──────────────┐      │
                                │   MEMORI     │──────┘  MemoryManager::learn()
                                └──────────────┘
```

Seluruh siklus dijalankan `AgentRuntime` **per giliran (tick)** dengan anggaran langkah
terbatas dan state tersimpan setiap langkah — sehingga pekerjaan panjang bisa dijeda,
dilanjutkan, atau diambil alih proses lain tanpa kehilangan konteks.

---

## B. Peta Berkas

| Lapisan | Berkas |
|---|---|
| Kontrak | `app/Agent/Contracts/` — `Tool`, `LlmProvider`, `MemoryStore`, `EmbeddingProvider` |
| Data (DTO) | `app/Agent/Data/` — `Plan`, `PlanStep`, `ToolResult`, `Verification`, `Reflection`, `MemoryBundle` … |
| Runtime | `app/Agent/Runtime/` — `AgentRuntime` (loop), `AgentTaskService`, `ApprovalService`, `AgentNotifier` |
| Perencanaan | `app/Agent/Planning/Planner.php` |
| Eksekusi | `app/Agent/Execution/` — `Executor`, `ErrorClassifier`, `RecoveryPlanner`, `Recovery` |
| Pemeriksaan | `app/Agent/Verification/Verifier.php` |
| Refleksi | `app/Agent/Reflection/Reflector.php` |
| Memori | `app/Agent/Memory/` — `MemoryManager`, `DatabaseMemoryStore`, `ExperienceScorer`, `HashingEmbedder`, `Redactor` |
| Kebijakan | `app/Agent/Policy/` — `PolicyEngine`, `PolicyDecision` |
| Tool | `app/Agent/Tools/` — `ToolRegistry`, `BaseTool`, `Concrete/*` |
| LLM | `app/Agent/Llm/` — `LlmManager`, `Providers/AnthropicProvider`, `Providers/ScriptedProvider` |
| Akses luar | `app/Agent/Integrations/` — `IntegrationManager`, `IntegrationCatalog`, `Connectors/*` |
| Chatbot | `app/Agent/Channels/Telegram/` — `TelegramClient`, `TelegramBot` |
| Audit | `app/Agent/Events/` — `EventRecorder`, `EventType` |
| Basis data | `database/migrations/2026_08_03_*` (13 tabel `agent*`) |
| Antarmuka | `resources/js/Pages/Agent/` — `Index`, `TaskDetail`, `MemoryView`, `IntegrationsView`, `NewTaskModal` |

---

## C. Cara Agent Bekerja

1. **Terima** — `AgentTaskService::create()` mencatat pekerjaan (dari web, Telegram, CLI, atau
   penjadwal) beserta kunci idempotensi agar kiriman ganda tidak menjadi dua pekerjaan.
2. **Pahami** — jenis pekerjaan, tingkat risiko, dan bentuk hasil ditetapkan lebih dulu.
3. **Ingat** — memori relevan diambil dan diberi skor sebelum satu langkah pun disusun.
4. **Rencanakan** — prosedur yang terbukti dipakai bila ada; bila tidak, rencana disusun baru.
   Pelajaran lama disuntikkan ke input langkah (mis. pemetaan nama kolom).
5. **Saring kebijakan** — tiap langkah melewati `PolicyEngine`: risiko, kemampuan agent, dan
   izin aplikasi milik pemilik pekerjaan. Risiko tinggi berhenti menunggu persetujuan manusia.
6. **Jalankan** — `Executor` memvalidasi input, menegakkan idempotensi, memanggil tool,
   dan mencatat hasilnya (input/output diredaksi dari rahasia).
7. **Amati & periksa** — `Verifier` menguji kriteria yang dapat dibuktikan: berkas benar-benar
   ada, jumlah baris memadai, dan **angka pada dokumen cocok dengan hasil perhitungan**.
8. **Pulihkan** — kegagalan digolongkan lalu ditangani spesifik: petakan kolom, betulkan nama
   berkas, minta akses, susun ulang rencana, atau serahkan ke manusia. Tidak ada pengulangan buta.
9. **Refleksi & belajar** — pengalaman, pelajaran, dan prosedur disimpan; nilai memori yang
   dipakai naik/turun sesuai hasilnya.

### Berkas data

Berkas yang boleh dibaca agent diunggah lewat dasbor → tab **Berkas Data** (CSV/TSV, maks.
5 MB). Berkas tersimpan di ruang kerja privat aplikasi — bukan direktori publik — sehingga
tidak dapat diunduh lewat URL tebakan. Nama berkasnya disebut langsung di dalam instruksi:

> "Buat laporan penjualan bulan ini dari berkas **penjualan-2026-08.csv**"

### Membaca instruksi chat

Pengguna chat menuliskan keterangan di dalam kalimat, bukan di formulir. Tahap pemahaman
karena itu menarik keterangan yang **tersurat** — nama berkas (`penjualan-2026-08.csv`) dan
alamat email — menjadi konteks pekerjaan. Nilai yang sudah diisi pengguna secara eksplisit
tidak pernah ditimpa.

Bila berkas sumber tidak disebut sama sekali, agent **tidak menebak**: ia berhenti dan
bertanya sambil menyebutkan berkas apa saja yang tersedia. Menebak berkas berarti
menerbitkan laporan periode yang keliru — kegagalan yang jauh lebih mahal daripada berhenti.

### Status pekerjaan

`PENDING → PLANNING → EXECUTING ⇄ WAITING_APPROVAL / PAUSED → VERIFYING → COMPLETED | FAILED | CANCELLED`

---

## D. Arsitektur Memori

| Lapis | Tabel | Isi |
|---|---|---|
| Kerja (working) | kolom `working_memory` pada `agent_tasks` | observasi terakhir, kendala, pelajaran tertunda |
| Episodik | `agent_experiences` | "apa yang terjadi pada pekerjaan ini" beserta hasilnya |
| Semantik | `agent_memories` | pengetahuan umum (mis. isi lazim sebuah laporan penjualan) |
| Prosedural | `agent_procedures` | kerangka langkah yang terbukti, berversi |
| Performa | `agent_lessons`, `agent_tool_stats` | pelajaran & keberhasilan tiap tool per jenis pekerjaan |

**Log mentah ≠ memori.** Yang masuk memori adalah intisari yang sudah diredaksi:
`Redactor` menyamarkan kredensial di semua tempat, dan menyaring email/nomor telepon
sebelum apa pun masuk ke memori jangka panjang.

**Skor kegunaan memori** (`ExperienceScorer`):

```
skor = 0.45·kemiripan + 0.25·tingkat_keberhasilan + 0.15·keyakinan + 0.15·kebaruan
```

Bentuk penjumlahan berbobot dipilih agar satu faktor bernilai nol (mis. pengalaman baru
yang belum pernah dipakai ulang) tidak menghapus seluruh nilai memori. Memori usang tidak
dihapus — hanya ditekan prioritasnya (×0,2). Pelajaran yang saling bertentangan diselesaikan
dengan membandingkan keyakinan, lalu kebaruan.

Vektor dihasilkan `HashingEmbedder` (lokal, deterministik, tanpa jaringan). Karena berada di
balik `EmbeddingProvider` dan `MemoryStore`, penggantinya (vector database sungguhan) dapat
dipasang tanpa mengubah kode agent.

---

## E. Pembelajaran dari Pengalaman

Contoh nyata yang bisa dijalankan sendiri (`php artisan agent:demo --fresh`):

**Pekerjaan #1** — "Buat laporan penjualan Juli 2026 dari `penjualan-2026-07.csv`"

```
TOOL_FAILED        spreadsheet.read gagal: Kolom berikut tidak ada pada berkas: revenue.
RECOVERY_APPLIED   Kegagalan 'missing_column' → strategi 'adjust_inputs':
                   Memetakan kolom revenue → total_revenue lalu mengulang langkah.
TOOL_COMPLETED     Terbaca 8 baris …
TASK_COMPLETED     Pekerjaan selesai dengan keyakinan 90%.
LESSON_CREATED     Berkas penjualan-*-*.csv memakai penamaan kolom berbeda: revenue → total_revenue.
```

**Pekerjaan #2** — "Buat laporan penjualan Agustus 2026 dari `penjualan-2026-08.csv`"

```
MEMORY_RETRIEVED   Mengambil 1 pengalaman, 1 pelajaran, 1 prosedur relevan.
LESSON_APPLIED     1 pelajaran dari pekerjaan sebelumnya diterapkan pada rencana.
TASK_PLANNED       Rencana 3 langkah disusun (procedure, keyakinan 83%).
TOOL_COMPLETED     Terbaca 6 baris … (tanpa kegagalan sama sekali)
```

| | Pekerjaan #1 | Pekerjaan #2 |
|---|---|---|
| Percobaan ulang | 1 | **0** |
| Pengalaman dipakai | 0 | 1 |
| Pelajaran dipakai | 0 | 1 |
| Asal rencana | disusun baru | **prosedur terbukti** |
| Keyakinan rencana | 50% | **83%** |

Pelajaran sengaja diikat ke **keluarga berkas** (`penjualan-*-*.csv`), bukan satu berkas,
karena berkas bulanan berganti nama tiap periode sementara tata nama kolomnya tetap.

---

## F. Arsitektur Tool

Semua tool memenuhi satu kontrak (`describe / validate / execute`) dan didaftarkan lewat
`config/agent.php` → menambah kemampuan cukup dengan satu kelas baru.

| Tool | Risiko | Kegunaan |
|---|---|---|
| `clock.now` | rendah | waktu acuan |
| `agent.note` | rendah | merangkum tanpa efek samping |
| `spreadsheet.read` | rendah | membaca CSV/TSV yang diunggah di tab **Berkas Data** |
| `data.analyze` | rendah | total, rata-rata, pengelompokan |
| `data.compare` | rendah | rekonsiliasi dua sumber |
| `document.create` | rendah | menyusun dokumen (Markdown/HTML/PDF) |
| `file.write` | rendah | menulis berkas di ruang kerja |
| `tasks.search` | rendah | menelusuri papan tugas aplikasi |
| `tasks.report` | rendah | rekap status tugas |
| `email.draft` | rendah | menyusun draf email |
| `email.send` | **tinggi** | mengirim email (Microsoft 365 / SMTP aplikasi) |
| `calendar.create_event` | **tinggi** | membuat agenda (Microsoft 365 / Google Calendar) |
| `whatsapp.send` | **tinggi** | mengirim WhatsApp lewat gateway aplikasi |

**Idempotensi.** Kunci = `hash(task_id + tool + input)` — sengaja tanpa nomor langkah, agar
tindakan yang sama tidak terjadi dua kali walaupun rencana disusun ulang. Baris eksekusi
dipesan sebelum tool dipanggil; bila proses mati di tengah jalan, tindakan tak-idempoten
**tidak diulang** melainkan diserahkan ke manusia.

---

## G. Model Keamanan

- **Kebijakan wajib**: tidak ada tool yang berjalan tanpa melewati `PolicyEngine`.
- **Batas hak**: agent tidak pernah melampaui izin aplikasi milik pemilik pekerjaan
  (mis. `tasks.view` untuk membaca papan tugas).
- **Persetujuan manusia**: `email.send`, `calendar.create_event`, `whatsapp.send`, dan seluruh
  langkah berisiko tinggi berhenti di `WAITING_APPROVAL`.
- **Sandbox berkas**: tool berkas dikurung di `storage/app/agent/tasks/<id>/`; tidak ada
  eksekusi perintah shell sama sekali.
- **Rahasia**: kredensial tersimpan terenkripsi (`agent_integrations`), tidak pernah dikirim
  ke frontend, dan disaring `Redactor` sebelum masuk log maupun memori.
- **Webhook Telegram**: rahasia pada URL + header `X-Telegram-Bot-Api-Secret-Token` + pembatasan laju.
- **Chat asing**: perintah hanya dilayani untuk chat yang sudah ditautkan ke akun aplikasi
  lewat kode sekali pakai.
- **Batas eksekusi**: langkah per giliran, langkah per pekerjaan, percobaan ulang, dan
  penyusunan ulang rencana semuanya berbatas.
- **Agent tidak boleh mengubah kodenya sendiri** — perbaikan diri hanya lewat memori.

---

## H. Meminta Akses saat Awal Dijalankan

Agent selalu membuka dengan menjelaskan apa yang dibutuhkannya, untuk apa, dan cara
memberikannya — di tiga tempat:

```bash
php artisan agent:setup                 # panduan + status akses
php artisan agent:setup --interactive   # sekaligus menerima kredensialnya
php artisan agent:setup --verify        # uji ulang akses yang sudah tersambung
```

- **Dasbor** → menu *Asisten AI* → tab **Akses Tools** (spanduk peringatan selama masih ada
  akses yang kurang).
- **Telegram** → `/mulai` dan `/akses`.

Akses yang dikenali: **Telegram Bot** (wajib untuk chatbot), **Model Bahasa (Claude)**,
**Microsoft 365** (email & kalender), **Google Calendar**, **Email SMTP aplikasi**, dan
**WhatsApp** (memakai gateway yang sudah ada di aplikasi ini). Setiap akses boleh **ditolak** —
agent akan bekerja dengan kemampuan yang tersisa dan berterus terang bila ada yang tak bisa
dilakukan. Ketika sebuah pekerjaan tertahan karena akses, pekerjaan itu **lanjut sendiri**
begitu aksesnya diberikan (`agent:tick`).

---

## I. Chatbot Telegram

```bash
# Menyimpan token — pilih salah satu:
php artisan agent:setup --interactive                     # tanya-jawab di terminal
php artisan agent:setup --connect=telegram \
    --field=bot_token="$TELEGRAM_BOT_TOKEN"               # tanpa TTY (Docker/skrip deploy)

php artisan agent:telegram --poll                          # mode pengembangan (long polling)
php artisan agent:telegram --set-webhook=                  # mode produksi (URL dari APP_URL)
```

Token tersimpan terenkripsi di `agent_integrations`, tidak pernah dikirim ke antarmuka, dan
disaring dari seluruh pesan galat sebelum dicatat — termasuk galat jaringan Telegram yang
menyelipkan token di dalam URL.

Menautkan akun: dasbor → *Akses Tools* → **Hubungkan Telegram saya** → kirim
`/tautkan KODE` ke bot (kode berlaku 30 menit, sekali pakai).

| Perintah | Fungsi |
|---|---|
| teks bebas | menugaskan pekerjaan baru |
| `/tugas` | daftar pekerjaan |
| `/status <id>` | rincian langkah & hasil |
| `/setujui <id>` · `/tolak <id>` | memutuskan tindakan berisiko |
| `/jeda <id>` · `/lanjut <id>` · `/batal <id>` | kendali pekerjaan |
| `/akses` | akses yang dibutuhkan + panduannya |

---

## J. Menjalankan & Menguji

```bash
php artisan migrate --force            # 13 tabel agent + tabel antrean
php artisan db:seed                    # agent bawaan, katalog akses, berkas data contoh

php artisan agent:demo --fresh         # demo belajar dari pengalaman (bagian E)
php artisan agent:run "Buat laporan penjualan bulan ini" --watch
php artisan agent:tick                 # denyut kerja (otomatis tiap menit lewat penjadwal)

./vendor/bin/phpunit                   # 59 test, 280 assertion
```

### Menjalankan di server

Pada citra Docker aplikasi ini, `supervisord` menjalankan empat proses: php-fpm, nginx,
**penjadwal** (`schedule:work` → `agent:tick` tiap menit), dan **pekerja antrean**
(`queue:work`). Dengan `QUEUE_CONNECTION=database` (bawaan produksi) penugasan dari layar
web dan pesan masuk Telegram dibalas seketika sementara pekerjaannya berjalan di latar.

Bila pekerja antrean mati atau tidak dipasang, pekerjaan tidak hilang: penjadwal menyapu
pekerjaan berstatus PENDING setiap menit. Dengan `QUEUE_CONNECTION=sync` semuanya berjalan
langsung di dalam request — tetap benar, hanya tidak asinkron.

Agent **berjalan penuh tanpa kunci API mana pun**: perencananya memakai mesin heuristik
lokal yang deterministik (`AGENT_LLM_PROVIDER=scripted`), sehingga test dan demo dapat
diulang tanpa jaringan. Mengisi `ANTHROPIC_API_KEY` (atau memberikannya lewat layar akses)
otomatis mengalihkan pemahaman instruksi & penyusunan rencana ke Claude, tanpa mengubah
satu baris pun kode agent.

---

## K. Perbaikan Berikutnya yang Disarankan

1. **Penyimpanan vektor sungguhan** (pgvector/Qdrant) di balik `MemoryStore` ketika jumlah
   pengalaman menembus puluhan ribu.
2. **Agent spesialis** (riset, keuangan, dokumen) di atas abstraksi yang sudah ada, dengan
   pembagian tugas oleh agent utama.
3. **Tool baca email & kalender** (Graph `messages`, `calendarView`) agar agent bisa
   menindaklanjuti surat masuk, bukan hanya mengirim.
4. **Ringkasan mingguan otomatis** lewat penjadwal yang menugaskan pekerjaan rutin ke agent.
5. **Evaluasi berkala** yang menandai pelajaran usang ketika sumber datanya berubah bentuk.
