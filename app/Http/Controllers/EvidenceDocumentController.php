<?php

namespace App\Http\Controllers;

use App\Models\EvidenceDocument;
use App\Models\EvidenceTemplate;
use App\Models\Task;
use App\Services\EvidenceDocumentService;
use App\Support\HtmlSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceDocumentController extends Controller
{
    public function __construct(private readonly EvidenceDocumentService $service)
    {
    }

    /** Buat dokumen draft baru untuk sebuah task, dari template pilihan. */
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($task);
        $this->authorizeEditing();

        $data = $request->validate([
            'template_id' => 'nullable|uuid|exists:evidence_templates,id',
            'title'       => 'nullable|string|max:255',
            'number'      => 'nullable|string|max:100',
        ]);

        $template = null;

        if (! empty($data['template_id'])) {
            $template = EvidenceTemplate::availableTo(auth()->user()->organization_id)
                ->whereKey($data['template_id'])
                ->firstOrFail();
        }

        $document = $this->service->createForTask($task, $template, auth()->user(), array_filter([
            'title'  => $data['title'] ?? null,
            'number' => $data['number'] ?? null,
        ], fn ($v) => filled($v)));

        return back()->with('success', "Dokumen \"{$document->title}\" dibuat sebagai draf. Silakan lengkapi lalu tanda tangani.");
    }

    /** Simpan hasil penyuntingan dokumen (hanya selama masih berstatus draf). */
    public function update(Request $request, EvidenceDocument $document): RedirectResponse
    {
        $this->authorizeDocument($document);
        $this->authorizeEditing();

        if ($document->is_signed) {
            return back()->with('error', 'Dokumen yang sudah ditandatangani tidak dapat diubah. Buat dokumen baru bila perlu revisi.');
        }

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'number'       => 'nullable|string|max:100',
            'content_html' => 'required|string|max:200000',
            'orientation'  => 'nullable|in:portrait,landscape',
            'data'         => 'nullable|array',
            'data.*'       => 'nullable|string|max:1000',
        ]);

        $document->update([
            'title'        => $data['title'],
            'number'       => $data['number'] ?? null,
            'content_html' => HtmlSanitizer::clean($data['content_html']),
            'orientation'  => $data['orientation'] ?? $document->orientation,
            'data'         => $data['data'] ?? [],
        ]);

        return back()->with('success', 'Perubahan dokumen tersimpan.');
    }

    /**
     * Tanda tangani dokumen: tanda tangan disimpan, dokumen dibekukan, dan
     * PDF-nya dihasilkan otomatis sehingga siap dipakai menutup task.
     */
    public function sign(Request $request, EvidenceDocument $document): RedirectResponse
    {
        $this->authorizeDocument($document);
        $this->authorizeSigning($document);

        if ($document->is_signed) {
            return back()->with('error', 'Dokumen ini sudah ditandatangani.');
        }

        $data = $request->validate([
            'signer_name'     => 'required|string|max:255',
            'signer_position' => 'nullable|string|max:255',
            'signature_place' => 'nullable|string|max:120',
            'signature'       => 'required|string|max:4000000',
        ], [
            'signature.required' => 'Bubuhkan tanda tangan terlebih dahulu.',
        ]);

        try {
            $document = $this->service->sign($document, auth()->user(), $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Dokumen ditandatangani dan otomatis dijadikan PDF: {$document->pdf_original_name}");
    }

    /** Pratinjau siap-cetak di tab baru (Ctrl+P / Simpan sebagai PDF dari peramban). */
    public function print(EvidenceDocument $document): View
    {
        $this->authorizeDocument($document);

        $document->load(['task.pic', 'task.creator', 'task.project', 'template', 'signer']);

        return view('evidence.document', [
            'document'  => $document,
            'task'      => $document->task,
            'signature' => $this->service->signatureDataUri($document),
            'logo'      => $this->service->logoDataUri(),
            'forPdf'    => false,
        ]);
    }

    /** Unduh berkas PDF hasil penandatanganan. */
    public function download(EvidenceDocument $document): StreamedResponse
    {
        $this->authorizeDocument($document);

        abort_unless($document->pdf_path && Storage::disk('public')->exists($document->pdf_path), 404,
            'PDF belum tersedia — dokumen ini belum ditandatangani.');

        return Storage::disk('public')->download($document->pdf_path, $document->pdf_original_name);
    }

    public function destroy(EvidenceDocument $document): RedirectResponse
    {
        $this->authorizeDocument($document);
        $this->authorizeEditing();

        if ($document->is_signed && ! auth()->user()->hasAnyRole(['super_admin', 'approval'])) {
            return back()->with('error', 'Dokumen yang sudah ditandatangani hanya dapat dihapus oleh Super Admin atau Manajer.');
        }

        // Task yang sudah ditutup memakai dokumen ini tidak boleh kehilangan buktinya.
        if (Task::where('evidence_document_id', $document->id)->where('status', 'Done')->exists()) {
            return back()->with('error', 'Dokumen ini dipakai sebagai bukti penutupan task. Buka kembali task tersebut sebelum menghapus dokumen.');
        }

        $document->deleteFiles();
        $document->delete();

        return back()->with('success', 'Dokumen bukti dihapus.');
    }

    // ── Otorisasi ────────────────────────────────────────────────────

    private function authorizeTask(Task $task): void
    {
        abort_if($task->organization_id !== auth()->user()->organization_id, 403);
    }

    private function authorizeDocument(EvidenceDocument $document): void
    {
        abort_if($document->organization_id !== auth()->user()->organization_id, 403);
    }

    private function authorizeEditing(): void
    {
        abort_unless(auth()->user()->can('tasks.create') || auth()->user()->can('tasks.edit'), 403);
    }

    /** Yang boleh membubuhkan tanda tangan: PIC task itu sendiri atau atasan. */
    private function authorizeSigning(EvidenceDocument $document): void
    {
        $user = auth()->user();

        $isPic = $document->task?->pic_id === $user->id;

        abort_unless($isPic || $user->hasAnyRole(['super_admin', 'approval', 'reviewer']), 403,
            'Hanya PIC task ini (atau Manajer/Reviewer) yang dapat menandatangani dokumen.');
    }
}
