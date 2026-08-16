<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalAttachment;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    /** Ambang nilai yang mewajibkan approval Level 2 (Direktur Utama / Super Admin). */
    private const DIRECTOR_THRESHOLD = 50_000_000;

    /**
     * Form Jurnal (1.1) + Laporan Jurnal (1.2) + alur approval bertingkat.
     */
    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $user  = auth()->user();
        $year  = (int) $request->get('year', now()->year);

        $approveLevel = $this->approveLevelFor($user);

        $entries = JournalEntry::where('organization_id', $orgId)
            ->whereYear('entry_date', $year)
            ->with(['lines.account:id,code,name', 'attachments', 'creator:id,name', 'approvals.user:id,name'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('entry_no', 'desc')
            ->get()
            ->map(function ($e) use ($user, $approveLevel) {
                $totalDebit = (float) $e->lines->sum('debit');
                return [
                    'id'            => $e->id,
                    'entry_no'      => $e->entry_no,
                    'entry_date'    => $e->entry_date->toDateString(),
                    'description'   => $e->description,
                    'is_posted'     => $e->is_posted,
                    'status'        => $e->status ?? 'draft',
                    'current_level' => $e->current_level ?? 0,
                    'reject_reason' => $e->reject_reason,
                    'created_by'    => $e->creator?->name,
                    'lines'         => $e->lines->map(fn ($l) => [
                        'account_id'   => $l->account_id,
                        'account_code' => $l->account?->code,
                        'account_name' => $l->account?->name,
                        'aux_code'     => $l->aux_code,
                        'debit'        => (float) $l->debit,
                        'credit'       => (float) $l->credit,
                        'memo'         => $l->memo,
                    ]),
                    'attachments' => $e->attachments->map(fn ($a) => [
                        'id'   => $a->id,
                        'name' => $a->original_name,
                        'url'  => Storage::disk('public')->url($a->path),
                    ]),
                    'approvals' => $e->approvals->sortBy('created_at')->values()->map(fn ($a) => [
                        'level'  => $a->level,
                        'action' => $a->action,
                        'user'   => $a->user?->name,
                        'notes'  => $a->notes,
                        'at'     => $a->created_at?->toDateTimeString(),
                    ]),
                    'total_debit'  => $totalDebit,
                    'total_credit' => (float) $e->lines->sum('credit'),
                    'can_edit'     => $this->canEdit($e, $user),
                    'can_submit'   => $this->canEdit($e, $user) && in_array(($e->status ?? 'draft'), ['draft', 'rejected'], true),
                    'can_approve'  => $this->canApprove($e, $user, $approveLevel),
                    'needs_level2' => $totalDebit > self::DIRECTOR_THRESHOLD,
                ];
            });

        return Inertia::render('Books/Journal', [
            'accounts' => Account::where('organization_id', $orgId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'account_type', 'normal_balance', 'report', 'type']),
            'entries'       => $entries,
            'year'          => $year,
            'can_create'    => $user->can('books.create') || $user->hasRole('super_admin'),
            'approve_level' => $approveLevel,
            'threshold'     => self::DIRECTOR_THRESHOLD,
            'aux_codes'     => $this->auxCodes($orgId),
        ]);
    }

    public function store(Request $request)
    {
        $orgId     = auth()->user()->organization_id;
        $validated = $this->validateEntry($request);
        $submit    = $request->input('action') === 'submit';

        [$totalDebit, $totalCredit] = $this->totals($validated['lines']);
        if (round($totalDebit, 2) !== round($totalCredit, 2) || $totalDebit <= 0) {
            return back()->with('error', 'Jurnal tidak seimbang: total debet harus sama dengan total kredit dan tidak boleh nol.');
        }

        DB::transaction(function () use ($orgId, $validated, $request, $submit) {
            $entry = JournalEntry::create([
                'organization_id' => $orgId,
                'entry_no'        => $this->generateEntryNo($orgId),
                'entry_date'      => $validated['entry_date'],
                'description'     => $validated['description'],
                'is_posted'       => false,
                'status'          => 'draft',
                'current_level'   => 0,
                'created_by'      => auth()->id(),
            ]);

            $this->syncLines($entry, $validated['lines']);
            $this->storeAttachments($entry, $request);

            if ($submit) {
                $this->doSubmit($entry);
            }
        });

        return back()->with('success', $submit ? 'Jurnal diajukan untuk approval' : 'Jurnal disimpan sebagai draft');
    }

    public function update(Request $request, JournalEntry $journal)
    {
        abort_unless($this->canEdit($journal, auth()->user()), 403);

        $validated = $this->validateEntry($request);

        [$totalDebit, $totalCredit] = $this->totals($validated['lines']);
        if (round($totalDebit, 2) !== round($totalCredit, 2) || $totalDebit <= 0) {
            return back()->with('error', 'Jurnal tidak seimbang: total debet harus sama dengan total kredit dan tidak boleh nol.');
        }

        DB::transaction(function () use ($journal, $validated, $request) {
            $journal->update([
                'entry_date'  => $validated['entry_date'],
                'description' => $validated['description'],
            ]);
            $journal->lines()->delete();
            $this->syncLines($journal, $validated['lines']);
            $this->storeAttachments($journal, $request);
        });

        return back()->with('success', 'Jurnal berhasil diperbarui');
    }

    /** Ajukan jurnal draft/ditolak untuk approval (Level 1). */
    public function submit(JournalEntry $journal)
    {
        abort_unless($this->canEdit($journal, auth()->user()), 403);
        abort_unless(in_array($journal->status, ['draft', 'rejected'], true), 422);

        $this->doSubmit($journal);

        return back()->with('success', 'Jurnal diajukan untuk approval');
    }

    /** Setujui jurnal pada level berjalan; posting otomatis bila sudah penuh. */
    public function approve(Request $request, JournalEntry $journal)
    {
        $user = auth()->user();
        abort_unless($this->canApprove($journal, $user, $this->approveLevelFor($user)), 403);

        $notes = $request->input('notes');
        $total = (float) $journal->lines()->sum('debit');

        DB::transaction(function () use ($journal, $user, $notes, $total) {
            $journal->approvals()->create([
                'level' => $journal->current_level, 'user_id' => $user->id,
                'action' => 'approved', 'notes' => $notes, 'created_at' => now(),
            ]);

            if ($journal->current_level === 1 && $total > self::DIRECTOR_THRESHOLD) {
                // Butuh approval tambahan Direktur (Level 2).
                $journal->update(['current_level' => 2, 'status' => 'pending']);
            } else {
                // Disetujui penuh → posting otomatis ke buku besar.
                $journal->update(['status' => 'posted', 'is_posted' => true, 'current_level' => 0]);
            }
        });

        return back()->with('success', $journal->is_posted
            ? 'Jurnal disetujui penuh & diposting ke buku besar'
            : 'Disetujui Level 1 — diteruskan ke Direktur (jumlah > ' . number_format(self::DIRECTOR_THRESHOLD, 0, ',', '.') . ')');
    }

    public function reject(Request $request, JournalEntry $journal)
    {
        $user = auth()->user();
        abort_unless($this->canApprove($journal, $user, $this->approveLevelFor($user)), 403);

        $data = $request->validate(['reason' => 'required|string|max:255']);

        $journal->approvals()->create([
            'level' => $journal->current_level, 'user_id' => $user->id,
            'action' => 'rejected', 'notes' => $data['reason'], 'created_at' => now(),
        ]);
        $journal->update(['status' => 'rejected', 'current_level' => 0, 'reject_reason' => $data['reason']]);

        return back()->with('success', 'Jurnal ditolak dan dikembalikan ke pembuat');
    }

    public function destroy(JournalEntry $journal)
    {
        $user = auth()->user();
        abort_unless($journal->organization_id === $user->organization_id, 403);
        abort_unless($user->hasRole('super_admin') || $journal->created_by === $user->id, 403);

        foreach ($journal->attachments as $att) {
            Storage::disk('public')->delete($att->path);
        }
        $journal->attachments()->delete();
        $journal->approvals()->delete();
        $journal->lines()->delete();
        $journal->delete();

        return back()->with('success', 'Jurnal dihapus');
    }

    public function destroyAttachment(JournalAttachment $attachment)
    {
        $entry = $attachment->journalEntry;
        abort_unless($entry && $entry->organization_id === auth()->user()->organization_id, 403);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Dokumen pendukung dihapus');
    }

    // ── Otorisasi & alur ─────────────────────────────────────

    /** Level approval maksimum yang dimiliki user (0 = tidak boleh). */
    private function approveLevelFor($user): int
    {
        if ($user->hasRole('super_admin')) return 2;            // Direktur Utama
        if ($user->hasAnyRole(['reviewer', 'approval'])) return 1; // Finance Manager (L1)
        return 0;
    }

    private function canEdit(JournalEntry $e, $user): bool
    {
        if ($e->organization_id !== $user->organization_id) return false;
        if (! in_array(($e->status ?? 'draft'), ['draft', 'rejected'], true)) return false;
        return $user->hasRole('super_admin') || $e->created_by === $user->id;
    }

    private function canApprove(JournalEntry $e, $user, int $approveLevel): bool
    {
        if ($e->organization_id !== $user->organization_id) return false;
        if (($e->status ?? 'draft') !== 'pending') return false;
        if ($user->hasRole('super_admin')) return true;          // Direktur boleh level manapun
        return $approveLevel >= 1 && (int) $e->current_level === 1;
    }

    private function doSubmit(JournalEntry $entry): void
    {
        $entry->update(['status' => 'pending', 'current_level' => 1, 'reject_reason' => null]);
        $entry->approvals()->create([
            'level' => 1, 'user_id' => auth()->id(), 'action' => 'submitted',
            'notes' => null, 'created_at' => now(),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function validateEntry(Request $request): array
    {
        $validated = $request->validate([
            'entry_date'         => 'required|date',
            'description'        => 'required|string|max:255',
            'lines'              => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.aux_code'   => 'nullable|string|max:50',
            'lines.*.debit'      => 'nullable|numeric|min:0',
            'lines.*.credit'     => 'nullable|numeric|min:0',
            'lines.*.memo'       => 'nullable|string|max:255',
            'documents'          => 'nullable|array',
            'documents.*'        => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $validated['lines'] = collect($validated['lines'])->map(fn ($l) => [
            ...$l,
            'debit'  => (float) ($l['debit'] ?? 0),
            'credit' => (float) ($l['credit'] ?? 0),
        ])->all();

        return $validated;
    }

    private function totals(array $lines): array
    {
        return [collect($lines)->sum('debit'), collect($lines)->sum('credit')];
    }

    private function syncLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $line) {
            if ((float) $line['debit'] === 0.0 && (float) $line['credit'] === 0.0) {
                continue;
            }
            $entry->lines()->create([
                'account_id' => $line['account_id'],
                'aux_code'   => $line['aux_code'] ?? null,
                'debit'      => $line['debit'],
                'credit'     => $line['credit'],
                'memo'       => $line['memo'] ?? null,
            ]);
        }
    }

    private function storeAttachments(JournalEntry $entry, Request $request): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }
        foreach ($request->file('documents') as $file) {
            $path = $file->store('journal-docs/' . $entry->id, 'public');
            $entry->attachments()->create([
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
            ]);
        }
    }

    private function auxCodes(string $orgId): array
    {
        $vendors = Vendor::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn ($v) => ['code' => $v->code, 'label' => $v->code . ' — ' . $v->name . ' (Vendor)']);

        $customers = \App\Models\UnloadingPoint::where('organization_id', $orgId)
            ->whereNotNull('code')
            ->orderBy('code')
            ->get(['code', 'customer_name', 'name'])
            ->map(fn ($c) => ['code' => $c->code, 'label' => $c->code . ' — ' . ($c->customer_name ?: $c->name) . ' (Customer)']);

        return $vendors->concat($customers)->values()->all();
    }

    private function generateEntryNo(string $orgId): string
    {
        $prefix = 'JE-' . now()->format('Ym') . '-';
        $count  = JournalEntry::where('organization_id', $orgId)
            ->where('entry_no', 'like', $prefix . '%')
            ->count();

        return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
