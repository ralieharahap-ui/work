<?php

namespace App\Http\Controllers;

use App\Models\FundingCreditor;
use App\Models\FundingCreditorDocument;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FundingCreditorController extends Controller
{
    /**
     * Master Kreditur Pendanaan (Investor/Bank) — kewajiban pendanaan operasi,
     * terpisah dari utang usaha (Vendor). Saldo dihitung otomatis dari jurnal
     * Posted yang baris utang-nya diberi kode bantu = kode kreditur.
     */
    public function index(): Response
    {
        $orgId = auth()->user()->organization_id;

        $creditors = FundingCreditor::where('organization_id', $orgId)
            ->with(['documents' => fn ($q) => $q->orderBy('created_at')])
            ->orderBy('code')->get();
        $movements = $this->movementsByCode($orgId);

        $rows = $creditors->map(function ($c) use ($movements) {
            $opening = (float) $c->payable_balance;
            $mv      = (float) ($movements[$c->code] ?? 0);
            $balance = $opening + $mv;
            return [
                'id'              => $c->id,
                'code'            => $c->code,
                'name'            => $c->name,
                'category'        => $c->category,
                'phone'           => $c->phone,
                'address'         => $c->address,
                'account_no'      => $c->account_no,
                'interest_rate'   => $c->interest_rate,
                'interest_period' => $c->interest_period ?: 'tahun',
                'loan_ceiling'    => $c->loan_ceiling,
                'maturity_date'   => $c->maturity_date?->toDateString(),
                'is_active'       => $c->is_active,
                'opening_balance' => $opening,
                'movement'        => $mv,
                'current_balance' => $balance,
                'due_soon'        => $c->maturity_date && $c->maturity_date->diffInDays(now(), false) > -30 && $c->maturity_date->isFuture(),
                'documents'       => $c->documents->map(fn ($d) => [
                    'id'            => $d->id,
                    'doc_type'      => $d->doc_type,
                    'original_name' => $d->original_name,
                    'mime'          => $d->mime,
                    'size'          => $d->size,
                    'uploaded_at'   => $d->created_at?->toDateTimeString(),
                ])->values(),
            ];
        });

        return Inertia::render('Books/Creditors', [
            'creditors'  => $rows,
            'summary'    => [
                'total_investor'          => $rows->where('category', 'investor')->sum('current_balance'),
                'total_investor_internal' => $rows->where('category', 'investor_internal')->sum('current_balance'),
                'total_bank'              => $rows->where('category', 'bank')->sum('current_balance'),
                'due_soon_count'          => $rows->where('due_soon', true)->count(),
            ],
            'categories' => FundingCreditor::CATEGORIES,
            'doc_types'  => collect(FundingCreditorDocument::TYPES)->map(fn ($t, $k) => ['key' => $k, 'label' => $t[0], 'multiple' => $t[1]])->values(),
            'can_manage' => auth()->user()->hasRole('super_admin'),
            'can_delete' => auth()->user()->hasRole('super_admin'), // hapus kreditur khusus Super Admin
            'can_upload' => $this->canUpload(),
        ]);
    }

    public function show(FundingCreditor $creditor): Response
    {
        abort_unless($creditor->organization_id === auth()->user()->organization_id, 403);

        $lines = JournalLine::where('aux_code', $creditor->code)
            ->whereHas('account', fn ($q) => $q->whereIn('account_type', self::FUNDING_GROUPS))
            ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $creditor->organization_id)
                ->where('is_posted', true))
            ->with(['journalEntry:id,entry_no,entry_date,description', 'account:id,code,name'])
            ->get()
            ->sortBy(fn ($l) => [$l->journalEntry->entry_date->toDateString(), $l->journalEntry->entry_no])
            ->values();

        $running = (float) $creditor->payable_balance;
        $rows = $lines->map(function ($l) use (&$running) {
            $debit  = (float) $l->debit;
            $credit = (float) $l->credit;
            $running += $credit - $debit; // kewajiban bertambah bila kredit, berkurang bila debit
            return [
                'entry_no'    => $l->journalEntry->entry_no,
                'entry_date'  => $l->journalEntry->entry_date->toDateString(),
                'description' => $l->memo ?: $l->journalEntry->description,
                'account'     => $l->account->code . ' — ' . $l->account->name,
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => $running,
            ];
        });

        return Inertia::render('Books/CreditorLedger', [
            'creditor'        => $creditor->only(['id', 'code', 'name', 'category', 'phone', 'account_no', 'interest_rate', 'interest_period', 'maturity_date']),
            'opening_balance' => (float) $creditor->payable_balance,
            'rows'            => $rows,
            'current_balance' => $running,
            'total_debit'     => $rows->sum('debit'),
            'total_credit'    => $rows->sum('credit'),
        ]);
    }

    public function store(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        $creditor = FundingCreditor::create([...$this->validated($request, $orgId), 'organization_id' => $orgId]);
        $this->storeFiles($creditor, 'kontrak', $request->file('contracts', []));

        return back()->with('success', 'Kreditur pendanaan berhasil ditambahkan');
    }

    public function update(Request $request, FundingCreditor $creditor)
    {
        abort_unless($creditor->organization_id === auth()->user()->organization_id, 403);

        $creditor->update($this->validated($request, $creditor->organization_id, $creditor->id));
        $this->storeFiles($creditor, 'kontrak', $request->file('contracts', []));

        return back()->with('success', 'Kreditur pendanaan berhasil diperbarui');
    }

    public function destroy(FundingCreditor $creditor)
    {
        abort_unless($creditor->organization_id === auth()->user()->organization_id, 403);
        abort_unless(auth()->user()->hasRole('super_admin'), 403, 'Hapus kreditur hanya dapat dilakukan oleh Super Admin');

        Storage::disk('local')->deleteDirectory('funding-creditor-docs/' . $creditor->id);
        $creditor->documents()->delete();
        $creditor->delete();

        return back()->with('success', 'Kreditur pendanaan dihapus');
    }

    // ── Dokumen underlying & pendukung ───────────────────────

    public function uploadDocuments(Request $request, FundingCreditor $creditor)
    {
        abort_unless($creditor->organization_id === auth()->user()->organization_id, 403);
        abort_unless($this->canUpload(), 403);

        $data = $request->validate([
            'doc_type' => ['required', Rule::in(array_keys(FundingCreditorDocument::TYPES))],
            'files'    => 'required|array|min:1',
            'files.*'  => 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ], [
            'files.*.max'   => 'Ukuran file maksimal 10 MB.',
            'files.*.mimes' => 'Format file harus PDF, gambar (JPG/PNG/WEBP), Word, atau Excel.',
        ]);

        $multiple = FundingCreditorDocument::TYPES[$data['doc_type']][1];
        $files    = $request->file('files');

        if (! $multiple) {
            // Dokumen tunggal (Bukti Transfer): file baru menggantikan file lama
            $files = [end($files)];
            foreach ($creditor->documents()->where('doc_type', $data['doc_type'])->get() as $old) {
                Storage::disk('local')->delete($old->path);
                $old->delete();
            }
        }

        $this->storeFiles($creditor, $data['doc_type'], $files);

        return back()->with('success', FundingCreditorDocument::TYPES[$data['doc_type']][0] . ' berhasil diunggah');
    }

    public function viewDocument(FundingCreditorDocument $document)
    {
        $this->authorizeDocument($document);

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Content-Type'        => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_name) . '"',
        ]);
    }

    public function downloadDocument(FundingCreditorDocument $document)
    {
        $this->authorizeDocument($document);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroyDocument(FundingCreditorDocument $document)
    {
        $this->authorizeDocument($document);
        abort_unless($this->canUpload(), 403);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus');
    }

    private function authorizeDocument(FundingCreditorDocument $document): void
    {
        abort_unless($document->creditor && $document->creditor->organization_id === auth()->user()->organization_id, 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404, 'File dokumen tidak ditemukan');
    }

    /** Super Admin atau user dengan izin "Kelola Kreditur Pendanaan" boleh mengunggah/menghapus dokumen. */
    private function canUpload(): bool
    {
        $user = auth()->user();
        return $user->hasRole('super_admin') || $user->can('books.creditors.manage');
    }

    private function storeFiles(FundingCreditor $creditor, string $type, array $files): void
    {
        foreach ($files as $file) {
            if (! $file) continue;
            $creditor->documents()->create([
                'doc_type'      => $type,
                'path'          => $file->store('funding-creditor-docs/' . $creditor->id . '/' . $type, 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => auth()->id(),
            ]);
        }
    }

    /**
     * Kelompok akun pendanaan (COA revisi PSAK 2026): pinjaman jangka pendek/panjang & hutang pihak
     * berelasi (investor internal). 'Utang Pendanaan' = nama kelompok COA lama, tetap dikenali.
     */
    private const FUNDING_GROUPS = [
        'Pinjaman Jangka Pendek / Bagian Lancar', 'Pinjaman Jangka Panjang', 'Hutang Pihak Berelasi', 'Utang Pendanaan',
    ];

    /** Total pergerakan kewajiban (kredit - debit) per kode bantu, dari jurnal Posted (akun pendanaan). */
    private function movementsByCode(string $orgId): array
    {
        return JournalLine::selectRaw('aux_code, SUM(credit - debit) as mv')
            ->whereNotNull('aux_code')
            ->whereHas('account', fn ($q) => $q->whereIn('account_type', self::FUNDING_GROUPS))
            ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $orgId)->where('is_posted', true))
            ->groupBy('aux_code')
            ->pluck('mv', 'aux_code')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function validated(Request $request, string $orgId, ?string $ignoreId = null): array
    {
        $request->validate([
            'contracts'   => 'nullable|array',
            'contracts.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx',
        ], [
            'contracts.*.max'   => 'Ukuran file kontrak maksimal 10 MB.',
            'contracts.*.mimes' => 'File kontrak harus PDF, gambar, atau Word.',
        ]);

        return $request->validate([
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('funding_creditors', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))
                    ->ignore($ignoreId),
            ],
            'name'            => 'required|string|max:255',
            'category'        => ['required', Rule::in(array_keys(FundingCreditor::CATEGORIES))],
            'phone'           => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:255',
            'account_no'      => 'nullable|string|max:50',
            'interest_rate'   => 'nullable|numeric',
            'interest_period' => 'required|in:tahun,bulan',
            'loan_ceiling'    => 'nullable|numeric',
            'maturity_date'   => 'nullable|date',
            'payable_balance' => 'nullable|numeric',
            'is_active'       => 'boolean',
        ]);
    }
}
