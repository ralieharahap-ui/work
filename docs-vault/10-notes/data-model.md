---
title: Data Model (Model & Relasi Utama)
aliases: [Model Data, Data Model]
tags: [teknis, referensi]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[teknis-moc]]", "[[chart-of-accounts]]", "[[jurnal-umum]]", "[[subledger-vendor]]", "[[subledger-customer]]"]
---
# Data Model (Model & Relasi Utama)

## Konvensi umum
- Model memakai `HasUuids` (primary key UUID, bukan auto-increment).
- Kode akun format `X-XXXX` — lihat [[chart-of-accounts]].
- `JournalLine` memakai `$timestamps = false`.

## Model kunci
- `Account` — akun COA, punya `account_type`, `control_account`.
- `JournalEntry` — header jurnal, status approval, `is_posted`.
- `JournalLine` — baris jurnal, `aux_code` untuk subledger.
- `JournalApproval` — jejak approval berjenjang, lihat [[multi-level-approval]].
- `JournalAttachment` — lampiran bukti jurnal.
- `Document` — dokumen template (Design/Generate), lihat [[template-dokumen]].
- `FixedAsset` — aset tetap, lihat [[aset-tetap]].
- `Vendor` — master vendor + saldo utang, lihat [[subledger-vendor]].
- `UnloadingPoint` — Titik Bongkar, **dipakai ulang sebagai entitas Customer** (field `code`, `receivable_balance` ditambahkan) — lihat [[subledger-customer]].

## Pola Inertia (frontend)
- `useForm` + `transform()` untuk menyuntik data final sebelum kirim (menghindari race async `setData`).
- Upload file butuh `forceFormData: true`.
- Update dengan file: `router.post(url, {...data, _method: 'put'})` (bukan `router.put` langsung, karena multipart).
- **Jebakan closure**: dua panggilan `setData`/`setMeta` berurutan dalam fungsi yang sama memakai state lama dari closure yang sama — panggilan kedua bisa menimpa balik hasil panggilan pertama. Selalu gabungkan jadi satu panggilan jika mengubah lebih dari satu bagian state sekaligus. Lihat [[tabel-kustom-dokumen]] untuk kasus nyata.
