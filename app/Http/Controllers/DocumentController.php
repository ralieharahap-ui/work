<?php

namespace App\Http\Controllers;

use App\Models\CalculationScenario;
use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\PalmOilSource;
use App\Models\UnloadingPoint;
use Illuminate\Http\Request;
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
            ->with(['user:id,name', 'releaser:id,name'])
            ->latest('doc_date')->latest('created_at')
            ->get()
            ->map(fn ($d) => [
                'id'         => $d->id,
                'number'     => $d->number,
                'doc_date'   => $d->doc_date->toDateString(),
                'type_label' => $types[$d->type]['label'] ?? $d->type,
                'perihal'    => $d->meta['extra']['perihal'] ?? ($d->meta['party']['name'] ?? '—'),
                'user'       => $d->user?->name ?? '—',
                'releaser'   => $d->releaser?->name,
                'status'     => $d->status ?? 'on_review',
            ]);

        return Inertia::render('Documents/Log', [
            'documents' => $documents,
            'types'     => $types,
            'statuses'  => $this->statusOptions(),
            'filter'    => ['type' => $type, 'status' => $status, 'year' => $year],
        ]);
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
            'prefill'      => $this->buildPrefill($orgId, $type, $config),
            'next_number'  => $this->previewNumber($orgId, $config['prefix']),
        ]);
    }

    /**
     * Data pendukung form (dropdown skenario/jurnal/vendor/akun dsb.)
     * dipakai bersama oleh create() & edit().
     */
    private function buildPrefill(string $orgId, string $type, array $config): array
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
                ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        }

        return $prefill;
    }

    /**
     * Form revisi dokumen (reuse halaman Create dengan data terisi).
     * Akses dibatasi Super Admin & Reviewer pada route.
     */
    /** Dokumen terkunci bila sudah ditandatangani atau dirilis. */
    private function isLocked(Document $document): bool
    {
        return in_array($document->status, ['signed', 'released'], true);
    }

    public function edit(Document $document): Response
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);
        abort_if(
            $this->isLocked($document) && ! auth()->user()->hasRole('super_admin'),
            403,
            'Dokumen sudah ' . ($this->statusOptions()[$document->status] ?? $document->status)
                . ' dan terkunci. Hanya Super Admin yang dapat merevisi.'
        );

        $orgId  = $document->organization_id;
        $types  = $this->types();
        $type   = $document->type;
        $config = $types[$type] ?? ['label' => $type, 'active' => true, 'source' => null, 'prefix' => ''];

        return Inertia::render('Documents/Create', [
            'type'        => $type,
            'config'      => $config,
            'company'     => $this->company(),
            'prefill'     => $this->buildPrefill($orgId, $type, $config),
            'next_number' => $document->number,
            'document'    => [
                'id'       => $document->id,
                'number'   => $document->number,
                'doc_date' => $document->doc_date->toDateString(),
                'meta'     => $document->meta,
                'notes'    => $document->notes,
                'ref_type' => $document->ref_type,
                'ref_id'   => $document->ref_id,
            ],
        ]);
    }

    /**
     * Simpan revisi dokumen. Nomor, tipe, status & pembuat TIDAK berubah.
     * Penanda jurnal otomatis (posted_journal_id) dipertahankan agar tidak dobel.
     */
    public function update(Request $request, Document $document)
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);
        abort_if(
            $this->isLocked($document) && ! auth()->user()->hasRole('super_admin'),
            403,
            'Dokumen terkunci — hanya Super Admin yang dapat merevisi dokumen yang sudah ditandatangani/dirilis.'
        );

        $validated = $request->validate([
            'doc_date' => 'required|date',
            'meta'     => 'required|array',
            'notes'    => 'nullable|string',
            'ref_type' => 'nullable|string',
            'ref_id'   => 'nullable|string',
        ]);

        $meta = $validated['meta'];
        if (! empty($document->meta['extra']['posted_journal_id'])) {
            $meta['extra'] = array_merge($meta['extra'] ?? [], [
                'posted_journal_id' => $document->meta['extra']['posted_journal_id'],
            ]);
        }

        $document->update([
            'doc_date' => $validated['doc_date'],
            'meta'     => $meta,
            'notes'    => $validated['notes'] ?? null,
            'ref_type' => $validated['ref_type'] ?? $document->ref_type,
            'ref_id'   => $validated['ref_id'] ?? $document->ref_id,
        ]);

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Dokumen ' . $document->number . ' berhasil direvisi');
    }

    public function store(Request $request)
    {
        $orgId = auth()->user()->organization_id;
        $types = $this->types();

        $validated = $request->validate([
            'type'      => ['required', Rule::in(array_keys(array_filter($types, fn ($t) => $t['active'])))],
            'doc_date'  => 'required|date',
            'meta'      => 'required|array',
            'notes'     => 'nullable|string',
            'ref_type'  => 'nullable|string',
            'ref_id'    => 'nullable|string',
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

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Dokumen ' . $document->number . ' berhasil dibuat');
    }

    /**
     * Ubah status dokumen (Signed / Released / Cancelled / On Review).
     * Hanya Super Admin (berperan sebagai Direktur) — di-gate pada route.
     */
    public function setStatus(Request $request, Document $document)
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);

        $validated = $request->validate([
            'status' => 'required|in:on_review,signed,released,cancelled',
        ]);

        $document->status = $validated['status'];

        if ($validated['status'] === 'released') {
            $document->released_by = auth()->id();
            $document->released_at = now();
        }

        $document->save();

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

        return Inertia::render('Documents/Show', [
            'document' => [
                'id'          => $document->id,
                'type'        => $document->type,
                'number'      => $document->number,
                'doc_date'    => $document->doc_date->toDateString(),
                'status'      => $document->status ?? 'on_review',
                'released_by' => $document->releaser?->name,
                'released_at' => $document->released_at?->toDateTimeString(),
                'meta'        => $document->meta,
                'notes'       => $document->notes,
                'user'        => $document->user?->name,
            ],
            'config'      => $types[$document->type] ?? ['label' => $document->type],
            'company'     => $this->company(),
            'statuses'    => $this->statusOptions(),
            'can_release' => auth()->user()->hasRole('super_admin'),
            // Dokumen terkunci (signed/released) hanya boleh direvisi Super Admin.
            'can_edit'    => auth()->user()->hasRole('super_admin')
                             || (auth()->user()->hasRole('reviewer') && ! $this->isLocked($document)),
            'is_locked'   => $this->isLocked($document),
        ]);
    }

    /**
     * Verifikasi keaslian dokumen — PUBLIK (tanpa login), dibuka via QR.
     * Hanya dokumen yang sudah ditandatangani/dirilis yang dapat diverifikasi,
     * dan hanya menampilkan info keaslian (bukan rincian nilai/isi).
     */
    public function verify(string $id)
    {
        $document = Document::find($id);
        $valid = $document && $this->isLocked($document);

        return view('verify', [
            'valid'   => $valid,
            'company' => $this->company(),
            'doc'     => $valid ? [
                'number'      => $document->number,
                'type_label'  => $this->types()[$document->type]['label'] ?? $document->type,
                'doc_date'    => $document->doc_date->locale('id')->translatedFormat('d F Y'),
                'status'      => $this->statusOptions()[$document->status] ?? $document->status,
                'released_at' => $document->released_at?->locale('id')->translatedFormat('d F Y, H:i'),
                'code'        => 'GEP-' . strtoupper(substr(str_replace('-', '', $document->id), 0, 10)),
            ] : null,
        ]);
    }

    public function destroy(Document $document)
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);

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
