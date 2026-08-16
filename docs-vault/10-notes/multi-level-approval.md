---
title: Alur Multi-Level Approval Jurnal
aliases: [Multi Level Approval, Approval Jurnal, Persetujuan Berjenjang]
tags: [akuntansi, user-role, alur]
type: note
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[jurnal-umum]]", "[[role-permission]]", "[[user-role-moc]]"]
---
# Alur Multi-Level Approval Jurnal

Alur persetujuan berjenjang untuk `JournalEntry`. Controller: `JournalController`. Konstanta kunci: `DIRECTOR_THRESHOLD = 50_000_000` (Rp 50.000.000).

## Peran & level
- **Drafter**: membuat jurnal (`store` sebagai draft atau langsung submit).
- **Reviewer / Approval** → level approval **L1**.
- **Super Admin** → level approval **L2** (khusus nominal besar; berperan sebagai pengganti "Direktur Keuangan" yang tidak ada — hanya ada Direktur Utama).
- `approveLevelFor()` memetakan role ke level: `super_admin` = 2, `reviewer`/`approval` = 1.

## Alur keputusan
1. Drafter submit jurnal → status `pending approval` L1.
2. Reviewer/Approval menyetujui (`approve`):
   - Jika nominal **≤ Rp 50.000.000** → langsung `posted`.
   - Jika nominal **> Rp 50.000.000** → eskalasi ke L2 (super_admin) untuk persetujuan lanjutan → baru `posted`.
3. `reject` mengembalikan ke drafter dengan alasan.
4. `canEdit` / `canApprove` mengatur siapa boleh apa di setiap status.

## Riwayat keputusan desain
- Role dipetakan ke role yang **sudah ada** di sistem (bukan role baru).
- Ambang **>Rp 50 juta** dipilih karena struktur organisasi PT GEP tidak memiliki Direktur Keuangan — Direktur Utama menjadi otoritas L2.

## Verifikasi
Diuji end-to-end: submit → pending L1 → approve (>50jt) → eskalasi L2 → posted; dan submit → approve (<50jt) → langsung posted, dengan auto-jurnal yang benar (Debit Beban / Kredit Utang Pihak Berelasi).
