<?php

namespace App\Http\Controllers;

use App\Models\CalculationScenario;
use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\PalmOilSource;
use App\Models\UnloadingPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * Registry 10 jenis dokumen (poin 2 & 3).
     * 'active' => sudah bisa dibuat; sisanya bertahap (fase berikutnya).
     */
    private function types(): array
    {
        return [
            'invoice'         => ['label' => 'Invoice',                      'prefix' => 'INV', 'icon' => '🧾', 'active' => true,  'source' => 'scenario'],
            'faktur'          => ['label' => 'Faktur',                       'prefix' => 'FKT', 'icon' => '📑', 'active' => true,  'source' => 'scenario'],
            'kwitansi'        => ['label' => 'Kwitansi',                     'prefix' => 'KW',  'icon' => '💵', 'active' => true,  'source' => 'scenario'],
            'surat_jalan'     => ['label' => 'Surat Jalan',                  'prefix' => 'SJ',  'icon' => '🚚', 'active' => true,  'source' => 'shipment'],
            'voucher_jurnal'  => ['label' => 'Voucher Jurnal Entri',         'prefix' => 'VJ',  'icon' => '📝', 'active' => true,  'source' => 'journal'],
            'tanda_terima'    => ['label' => 'Tanda Terima Dokumen/Barang',  'prefix' => 'TT',  'icon' => '📥', 'active' => true,  'source' => null],
            'perjalanan_dinas'=> ['label' => 'Form Perjalanan Dinas',        'prefix' => 'PD',  'icon' => '✈️', 'active' => true,  'source' => null],
            'reimbursement'   => ['label' => 'Form Reimbursement',           'prefix' => 'RB',  'icon' => '💳', 'active' => true,  'source' => null],
            'po'              => ['label' => 'Purchase Order (PO)',          'prefix' => 'PO',  'icon' => '🛒', 'active' => true,  'source' => 'vendor'],
            'do'              => ['label' => 'Delivery Order (DO)',          'prefix' => 'DO',  'icon' => '📦', 'active' => true,  'source' => 'shipment'],
            'surat_resmi'     => ['label' => 'Surat Resmi',                  'prefix' => 'SR',  'icon' => '✉️', 'active' => true,  'source' => null],
        ];
    }

    /** Profil perusahaan untuk kop dokumen (dapat disesuaikan di sini). */
    private function company(): array
    {
        return [
            'name'    => 'PT GEOSYS ENERGI PRIMA',
            'address' => 'Signatur Park Grande CTA L1/02 Jl. Letjen MT. Haryono Kav.20, Kramatjati, Jakarta Timur 13630.',
            'website' => 'www.geosys-ep.com',
            'email'   => 'cs.admin@geosys-ep.com',
            'phone'   => '+6287893024936 / +628192430521',
            'logo'    => '/images/logo-gep.png',
        ];
    }

    /** Label & alur status dokumen. */
    private function statusOptions(): array
    {
        return [
            'on_review' => 'On Review',
            'signed'    => 'Signed',
            'released'  => 'Released',
            'cancelled' => 'Cancelled',
        ];
    }

    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $type  = $request->get('type');
        $year  = (int) $request->get('year', now()->year);

        $documents = Document::where('organization_id', $orgId)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->whereYear('doc_date', $year)
            ->with('user:id,name')
            ->latest('doc_date')
            ->latest('created_at')
            ->get()
            ->map(fn ($d) => [
                'id'       => $d->id,
                'type'     => $d->type,
                'number'   => $d->number,
                'doc_date' => $d->doc_date->toDateString(),
                'party'    => $d->meta['party']['name'] ?? '—',
                'total'    => $d->meta['amounts']['total'] ?? null,
                'status'   => $d->status ?? 'on_review',
                'user'     => $d->user?->name,
            ]);

        return Inertia::render('Documents/Index', [
            'types'     => $this->types(),
            'documents' => $documents,
            'filter'    => ['type' => $type, 'year' => $year],
            'statuses'  => $this->statusOptions(),
        ]);
    }

    /**
     * Dokumentasi — rekaman seluruh dokumen yang diterbitkan.
     * Kolom: nomor | tanggal | tipe | perihal | user perilis | status.
     */
    public function log(Request $request): Response
    {
        $orgId  = auth()->user()->organization_id;
        $type   = $request->get('type');
        $status = $request->get('status');
        $year   = (int) $request->get('year', now()->year);
        $types  = $this->types();

        $documents = Document::where('organization_id', $orgId)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->whereYear('doc_date', $year)
            ->with(['user:id,name', 'releaser:id,name', 'lastEditor:id,name'])
            ->withCount(['comments as comment_count' => fn ($q) => $q->where('kind', 'comment')])
            ->latest('doc_date')->latest('created_at')
            ->get()
            ->map(fn ($d) => [
                'id'             => $d->id,
                'number'         => $d->number,
                'doc_date'       => $d->doc_date->toDateString(),
                'type_label'     => $types[$d->type]['label'] ?? $d->type,
                'perihal'        => $d->meta['extra']['perihal'] ?? ($d->meta['party']['name'] ?? '—'),
                'user'           => $d->user?->name ?? '—',
                'releaser'       => $d->releaser?->name,
                'status'         => $d->status ?? 'on_review',
                'revision_count' => (int) $d->revision_count,
                'last_edited_by' => $d->lastEditor?->name,
                'last_edited_at' => $d->last_edited_at?->toDateTimeString(),
                'comment_count'  => (int) $d->comment_count,
            ]);

        return Inertia::render('Documents/Log', [
            'documents' => $documents,
            'types'     => $types,
            'statuses'  => $this->statusOptions(),
            'filter'    => ['type' => $type, 'status' => $status, 'year' => $year],
        ]);
    }

    /**
     * Reviewer ke atas (reviewer / approval / super_admin) boleh menyunting
     * draft dokumen & memberi komentar. Drafter biasa hanya bisa melihat.
     */
    private function canReview(): bool
    {
        return auth()->user()->hasAnyRole(['reviewer', 'approval', 'super_admin']);
    }

    /** Draft masih bisa disunting selama belum ditandatangani/dirilis/dibatalkan. */
    private function isEditable(Document $document): bool
    {
        return ($document->status ?? 'on_review') === 'on_review';
    }

    public function create(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $type  = $request->get('type', 'invoice');
        $types = $this->types();

        abort_unless(isset($types[$type]) && $types[$type]['active'], 404);

        $config = $types[$type];

        return Inertia::render('Documents/Create', [
            'type'         => $type,
            'config'       => $config,
            'company'      => $this->company(),
            'prefill'      => $this->prefillFor($type, $config, $orgId),
            'next_number'  => $this->previewNumber($orgId, $config['prefix']),
        ]);
    }

    /**
     * Form sunting draft — hanya reviewer ke atas & selama status masih On Review.
     * Memakai form yang sama dengan pembuatan dokumen (mode edit).
     */
    public function edit(Document $document): Response
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);
        abort_unless($this->canReview(), 403, 'Hanya Reviewer ke atas yang dapat menyunting dokumen.');
        abort_unless($this->isEditable($document), 403, 'Dokumen yang sudah ditandatangani/dirilis tidak dapat disunting.');

        $types  = $this->types();
        $type   = $document->type;
        $config = $types[$type] ?? ['label' => $type, 'prefix' => '', 'icon' => '📄', 'active' => true, 'source' => null];

        return Inertia::render('Documents/Create', [
            'type'        => $type,
            'config'      => $config,
            'company'     => $this->company(),
            'prefill'     => $this->prefillFor($type, $config, $document->organization_id),
            'next_number' => $document->number,
            'document'    => [
                'id'       => $document->id,
                'number'   => $document->number,
                'doc_date' => $document->doc_date->toDateString(),
                'meta'     => $document->meta,
                'notes'    => $document->notes,
                'ref_type' => $document->ref_type,
                'ref_id'   => $document->ref_id,
                'attachment_name' => $document->attachment_name,
            ],
        ]);
    }

    /**
     * Simpan hasil suntingan reviewer. Setiap perubahan menambah nomor revisi,
     * mencatat penyunting terakhir, dan menulis jejak otomatis di riwayat dokumen
     * sehingga terlihat pada halaman Dokumentasi.
     */
    public function update(Request $request, Document $document)
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);
        abort_unless($this->canReview(), 403, 'Hanya Reviewer ke atas yang dapat menyunting dokumen.');
        abort_unless($this->isEditable($document), 403, 'Dokumen yang sudah ditandatangani/dirilis tidak dapat disunting.');

        $this->decodeMeta($request);

        $validated = $request->validate([
            'doc_date'    => 'required|date',
            'meta'        => 'required|array',
            'notes'       => 'nullable|string',
            'edit_reason' => 'nullable|string|max:500',
            'attachment'  => self::ATTACHMENT_RULE,
        ]);

        $this->storeAttachment($request, $document);

        $document->update([
            'doc_date'       => $validated['doc_date'],
            'meta'           => $validated['meta'],
            'notes'          => $validated['notes'] ?? null,
            'last_edited_by' => auth()->id(),
            'last_edited_at' => now(),
            'revision_count' => (int) $document->revision_count + 1,
        ]);

        $document->comments()->create([
            'user_id' => auth()->id(),
            'kind'    => 'revision',
            'body'    => trim($validated['edit_reason'] ?? '') !== ''
                ? 'Revisi #' . $document->revision_count . ': ' . $validated['edit_reason']
                : 'Revisi #' . $document->revision_count . ' — dokumen disunting oleh reviewer.',
        ]);

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Dokumen ' . $document->number . ' diperbarui (revisi #' . $document->revision_count . ')');
    }

    /** Data pendukung form per jenis dokumen (dipakai create & edit). */
    private function prefillFor(string $type, array $config, string $orgId): array
    {
        $prefill = [];

        // Sumber data campuran: tarik data dari modul terkait bila tersedia.
        if (($config['source'] ?? null) === 'scenario') {
            $prefill['scenarios'] = CalculationScenario::where('organization_id', $orgId)
                ->latest()->get(['id', 'name', 'volume', 'price_customer', 'total_revenue', 'is_wapu']);
        } elseif (($config['source'] ?? null) === 'journal') {
            $prefill['journals'] = JournalEntry::where('organization_id', $orgId)
                ->where('is_posted', true)
                ->with('lines.account:id,code,name')
                ->latest('entry_date')->get()
                ->map(fn ($e) => [
                    'id'          => $e->id,
                    'entry_no'    => $e->entry_no,
                    'entry_date'  => $e->entry_date->toDateString(),
                    'description' => $e->description,
                    'lines'       => $e->lines->map(fn ($l) => [
                        'account' => $l->account?->code . ' — ' . $l->account?->name,
                        'debit'   => (float) $l->debit,
                        'credit'  => (float) $l->credit,
                    ]),
                ]);
        } elseif (($config['source'] ?? null) === 'shipment') {
            $prefill['sources']   = PalmOilSource::where('organization_id', $orgId)
                ->get(['id', 'name', 'city', 'province']);
            $prefill['customers'] = UnloadingPoint::where('organization_id', $orgId)
                ->get(['id', 'name', 'customer_name', 'city', 'province']);
        } elseif (($config['source'] ?? null) === 'vendor') {
            $prefill['vendors'] = \App\Models\Vendor::where('organization_id', $orgId)
                ->where('is_active', true)
                ->get(['id', 'code', 'name', 'address']);
        }

        // Dokumen yang otomatis membuat jurnal saat dirilis butuh daftar akun.
        if (in_array($type, ['perjalanan_dinas', 'reimbursement'], true)) {
            $prefill['accounts'] = \App\Models\Account::where('organization_id', $orgId)
                ->postable()->orderBy('code')->get(['id', 'code', 'name']);
        }

        return $prefill;
    }

    public function store(Request $request)
    {
        $orgId = auth()->user()->organization_id;
        $types = $this->types();

        $this->decodeMeta($request);

        $validated = $request->validate([
            'type'       => ['required', Rule::in(array_keys(array_filter($types, fn ($t) => $t['active'])))],
            'doc_date'   => 'required|date',
            'meta'       => 'required|array',
            'notes'      => 'nullable|string',
            'ref_type'   => 'nullable|string',
            'ref_id'     => 'nullable|string',
            'attachment' => self::ATTACHMENT_RULE,
        ]);

        $document = Document::create([
            'organization_id' => $orgId,
            'user_id'         => auth()->id(),
            'type'            => $validated['type'],
            'number'          => $this->generateNumber($orgId, $types[$validated['type']]['prefix']),
            'doc_date'        => $validated['doc_date'],
            'status'          => 'on_review',
            'meta'            => $validated['meta'],
            'ref_type'        => $validated['ref_type'] ?? null,
            'ref_id'          => $validated['ref_id'] ?? null,
            'notes'           => $validated['notes'] ?? null,
        ]);

        if ($this->storeAttachment($request, $document)) {
            $document->save();
        }

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Dokumen ' . $document->number . ' berhasil dibuat');
    }

    private const ATTACHMENT_RULE = 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx';

    /** Form ber-lampiran dikirim multipart; meta dikirim sebagai JSON agar tipe data utuh. */
    private function decodeMeta(Request $request): void
    {
        if (is_string($request->input('meta'))) {
            $request->merge(['meta' => json_decode($request->input('meta'), true)]);
        }
    }

    /** Simpan lampiran baru (menggantikan lampiran lama). Belum di-save ke DB. */
    private function storeAttachment(Request $request, Document $document): bool
    {
        if (! $request->hasFile('attachment')) {
            return false;
        }

        $file = $request->file('attachment');
        $old  = $document->attachment_path;

        $document->attachment_path = $file->store('document-attachments/' . $document->id, 'public');
        $document->attachment_name = $file->getClientOriginalName();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return true;
    }

    /** Hanya pemegang role approval / super_admin yang boleh menyetujui dokumen. */
    private function canApprove(): bool
    {
        return auth()->user()->hasAnyRole(['approval', 'super_admin']);
    }

    /**
     * Catat tahapan hierarki (review / approval) tanpa mengubah status dokumen.
     */
    public function endorse(Request $request, Document $document)
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);

        $step = $request->validate(['step' => 'required|in:review,approve'])['step'];

        if ($step === 'review') {
            abort_unless($this->canReview(), 403, 'Hanya Reviewer ke atas yang dapat mereview dokumen.');
            $document->update(['reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            $label = 'Dokumen telah direview.';
        } else {
            abort_unless($this->canApprove(), 403, 'Hanya Approval / Super Admin yang dapat menyetujui dokumen.');
            $document->update(['approved_by' => auth()->id(), 'approved_at' => now()]);
            $label = 'Dokumen telah disetujui (approval).';
        }

        $document->comments()->create(['user_id' => auth()->id(), 'kind' => 'status', 'body' => $label]);

        return back()->with('success', $label);
    }

    /**
     * Hierarki pembuatan dokumen. Reviewer jatuh ke penyunting terakhir
     * bila belum ada review eksplisit (dokumen lama).
     */
    private function trail(Document $document): array
    {
        $document->loadMissing(['user:id,name', 'reviewer:id,name', 'lastEditor:id,name', 'approver:id,name', 'signer:id,name', 'releaser:id,name']);

        $reviewer   = $document->reviewer ?? $document->lastEditor;
        $reviewedAt = $document->reviewed_at ?? ($document->reviewer ? null : $document->last_edited_at);

        $step = fn (string $label, $user, $at) => [
            'label' => $label,
            'name'  => $user?->name,
            'at'    => $at?->toIso8601String(),
        ];

        return [
            $step('Dibuat', $document->user, $document->created_at),
            $step('Direview', $reviewer, $reviewedAt),
            $step('Disetujui (Approval)', $document->approver, $document->approved_at),
            $step('Ditandatangani & Dirilis', $document->signer ?? $document->releaser, $document->signed_at ?? $document->released_at),
        ];
    }

    /** Halaman publik hasil scan QR — tanpa login. */
    public function verify(string $token): Response
    {
        $document = Document::where('verify_token', $token)->firstOrFail();
        $types    = $this->types();

        $isSigned   = (bool) $document->signed_at;
        $isReleased = ($document->status ?? '') === 'released';

        return Inertia::render('Documents/Verify', [
            'document' => [
                'number'     => $document->number,
                'type_label' => $types[$document->type]['label'] ?? $document->type,
                'doc_date'   => $document->doc_date->toDateString(),
                'perihal'    => $document->meta['extra']['perihal'] ?? null,
                'status'     => $document->status ?? 'on_review',
                'is_valid'   => $isSigned && $isReleased,
                'is_signed'  => $isSigned,
                'released_at'=> $document->released_at?->toDateTimeString(),
            ],
            'trail'   => $this->trail($document),
            'company' => $this->company(),
        ]);
    }

    /**
     * Ubah status dokumen (Signed / Released / Cancelled / On Review).
     * Hanya Super Admin (berperan sebagai Direktur) — di-gate pada route.
     */
    public function setStatus(Request $request, Document $document)
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);

        $validated = $request->validate([
            'status'    => 'required|in:on_review,signed,released,cancelled',
            'signature' => 'nullable|string', // data URL gambar tanda tangan (base64)
        ]);

        $document->status = $validated['status'];

        // Bubuhkan tanda tangan digital saat menandatangani.
        if ($validated['status'] === 'signed' && ! empty($validated['signature'])) {
            $meta = $document->meta;
            $meta['extra']['signature_data'] = $validated['signature'];
            $meta['extra']['signed_by']      = auth()->user()->name;
            $meta['extra']['signed_at']      = now()->toDateTimeString();
            $document->meta = $meta;
        }

        // Penandatangan + token QR verifikasi (dibuat sekali, tetap sama setelahnya).
        if ($validated['status'] === 'signed') {
            $document->signed_by    = auth()->id();
            $document->signed_at    = now();
            $document->verify_token ??= Str::random(40);
        }

        if ($validated['status'] === 'released') {
            $document->released_by = auth()->id();
            $document->released_at = now();
        }

        $document->save();

        // Jejak perubahan status ikut tercatat di riwayat dokumen.
        $document->comments()->create([
            'user_id' => auth()->id(),
            'kind'    => 'status',
            'body'    => 'Status diubah menjadi ' . $this->statusOptions()[$validated['status']] . '.',
        ]);

        // Saat dirilis: buat jurnal otomatis untuk dokumen berbasis biaya.
        $journalMsg = '';
        if ($validated['status'] === 'released') {
            $journalMsg = $this->postDocumentJournal($document);
        }

        return back()->with('success', 'Status dokumen diperbarui: ' . $this->statusOptions()[$validated['status']] . $journalMsg);
    }

    /**
     * Buat jurnal otomatis (posted) atas nilai biaya dokumen saat dirilis.
     * Akun debit/kredit dipilih pada form. Aman dari duplikasi.
     */
    private function postDocumentJournal(Document $document): string
    {
        if (! in_array($document->type, ['perjalanan_dinas', 'reimbursement'], true)) {
            return '';
        }

        $meta = $document->meta;
        if (! empty($meta['extra']['posted_journal_id'])) {
            return ''; // sudah dijurnal sebelumnya
        }

        $total    = (float) ($meta['amounts']['total'] ?? 0);
        $debitId  = $meta['extra']['debit_account_id'] ?? null;
        $creditId = $meta['extra']['credit_account_id'] ?? null;

        if ($total <= 0 || ! $debitId || ! $creditId) {
            return '';
        }

        $orgId = $document->organization_id;
        $valid = \App\Models\Account::where('organization_id', $orgId)
            ->whereIn('id', [$debitId, $creditId])->pluck('id')->all();
        if (! in_array($debitId, $valid, true) || ! in_array($creditId, $valid, true)) {
            return ' (jurnal otomatis dilewati: akun debit/kredit belum dipilih)';
        }

        $prefix = 'JE-' . now()->format('Ym') . '-';
        $seq    = \App\Models\JournalEntry::where('organization_id', $orgId)
            ->where('entry_no', 'like', $prefix . '%')->count() + 1;

        $entry = \App\Models\JournalEntry::create([
            'organization_id' => $orgId,
            'entry_no'        => $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'entry_date'      => $document->doc_date->toDateString(),
            'description'     => $this->types()[$document->type]['label'] . ' ' . $document->number
                                 . ' a.n. ' . ($meta['party']['name'] ?? '-'),
            'is_posted'       => true,
            'status'          => 'posted',
            'current_level'   => 0,
            'created_by'      => auth()->id(),
            'ref_type'        => 'document',
            'ref_id'          => $document->id,
        ]);
        $entry->lines()->create(['account_id' => $debitId,  'debit' => $total, 'credit' => 0, 'memo' => 'Otomatis dari ' . $document->number]);
        $entry->lines()->create(['account_id' => $creditId, 'debit' => 0, 'credit' => $total, 'memo' => 'Otomatis dari ' . $document->number]);

        // Tandai agar tidak dobel.
        $meta['extra']['posted_journal_id'] = $entry->id;
        $document->meta = $meta;
        $document->saveQuietly();

        return ' — jurnal otomatis ' . $entry->entry_no . ' dibuat & diposting';
    }

    public function show(Document $document): Response
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);

        $types = $this->types();

        $document->load(['lastEditor:id,name', 'comments.user:id,name']);

        return Inertia::render('Documents/Show', [
            'document' => [
                'id'             => $document->id,
                'type'           => $document->type,
                'number'         => $document->number,
                'doc_date'       => $document->doc_date->toDateString(),
                'status'         => $document->status ?? 'on_review',
                'released_by'    => $document->releaser?->name,
                'released_at'    => $document->released_at?->toDateTimeString(),
                'meta'           => $document->meta,
                'notes'          => $document->notes,
                'user'           => $document->user?->name,
                'last_edited_by' => $document->lastEditor?->name,
                'last_edited_at' => $document->last_edited_at?->toDateTimeString(),
                'revision_count' => (int) $document->revision_count,
                'attachment_url'  => $document->attachment_path ? Storage::disk('public')->url($document->attachment_path) : null,
                'attachment_name' => $document->attachment_name,
                'reviewed_at'     => $document->reviewed_at?->toDateTimeString(),
                'approved_at'     => $document->approved_at?->toDateTimeString(),
                'signed_at'       => $document->signed_at?->toDateTimeString(),
                'verify_url'      => $document->verify_token ? route('documents.verify', $document->verify_token) : null,
            ],
            'config'      => $types[$document->type] ?? ['label' => $document->type],
            'company'     => $this->company(),
            'statuses'    => $this->statusOptions(),
            'can_release' => auth()->user()->hasRole('super_admin'),
            'can_approve' => $this->canApprove(),
            'can_review'  => $this->canReview(),
            'can_edit'    => $this->canReview() && $this->isEditable($document),
            'comments'    => $document->comments->sortBy('created_at')->values()->map(fn ($c) => [
                'id'   => $c->id,
                'body' => $c->body,
                'kind' => $c->kind,
                'user' => $c->user?->name ?? 'Pengguna dihapus',
                'at'   => $c->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function destroy(Document $document)
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);

        if ($document->attachment_path) {
            Storage::disk('public')->delete($document->attachment_path);
        }
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Dokumen dihapus');
    }

    /**
     * Nomor final: [no urut]/GEP/[singkatan]/[bulan romawi]/[tahun].
     * Contoh: 001/GEP/SJ/VIII/2026. Urutan di-reset tiap tahun per jenis dokumen
     * (bulan hanya penanda bulan terbit).
     */
    private function generateNumber(string $orgId, string $prefix): string
    {
        $year  = (int) now()->year;
        $roman = $this->romanMonth((int) now()->month);

        // Nomor urut = maksimum nomor yang sudah ada untuk jenis (prefix) yang sama
        // pada tahun berjalan + 1 (tahan terhadap penghapusan agar tidak bertabrakan).
        $max = Document::where('organization_id', $orgId)
            ->where('number', 'like', '%/GEP/' . $prefix . '/%/' . $year)
            ->get(['number'])
            ->reduce(fn ($carry, $doc) => max($carry, (int) explode('/', $doc->number)[0]), 0);

        $seq = str_pad($max + 1, 3, '0', STR_PAD_LEFT);

        return $seq . '/GEP/' . $prefix . '/' . $roman . '/' . $year;
    }

    /** Bulan (1-12) ke angka Romawi. */
    private function romanMonth(int $m): string
    {
        $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
                7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

        return $map[$m] ?? (string) $m;
    }

    /** Pratinjau nomor berikutnya untuk ditampilkan di form. */
    private function previewNumber(string $orgId, string $prefix): string
    {
        return $this->generateNumber($orgId, $prefix);
    }
}
