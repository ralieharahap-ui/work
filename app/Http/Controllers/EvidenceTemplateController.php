<?php

namespace App\Http\Controllers;

use App\Models\EvidenceTemplate;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pengelolaan template dokumen & kertas kerja milik organisasi.
 *
 * Template bawaan sistem (`is_system`) bersifat baca-saja; untuk mengubahnya,
 * pengguna menduplikasi dulu menjadi template organisasi.
 */
class EvidenceTemplateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManaging();

        $data = $this->validateTemplate($request);

        EvidenceTemplate::create($data + [
            'organization_id' => auth()->user()->organization_id,
            'created_by'      => auth()->id(),
            'is_system'       => false,
            'is_active'       => true,
        ]);

        return back()->with('success', "Template \"{$data['name']}\" berhasil dibuat.");
    }

    /** Salin template bawaan sistem menjadi template milik organisasi. */
    public function duplicate(EvidenceTemplate $template): RedirectResponse
    {
        $this->authorizeManaging();
        $this->authorizeReading($template);

        $copy = EvidenceTemplate::create([
            'organization_id' => auth()->user()->organization_id,
            'created_by'      => auth()->id(),
            'code'            => null,
            'name'            => $template->name . ' (Salinan)',
            'description'     => $template->description,
            'category'        => $template->category,
            'body_html'       => $template->body_html,
            'fields'          => $template->fields,
            'orientation'     => $template->orientation,
            'is_system'       => false,
            'is_active'       => true,
        ]);

        return back()->with('success', "Template disalin menjadi \"{$copy->name}\" dan siap disesuaikan.");
    }

    public function update(Request $request, EvidenceTemplate $template): RedirectResponse
    {
        $this->authorizeManaging();
        $this->authorizeWriting($template);

        $template->update($this->validateTemplate($request));

        return back()->with('success', 'Template diperbarui.');
    }

    public function destroy(EvidenceTemplate $template): RedirectResponse
    {
        $this->authorizeManaging();
        $this->authorizeWriting($template);

        // Dokumen yang sudah dibuat tetap utuh; template hanya dinonaktifkan
        // bila masih dirujuk agar riwayatnya tidak putus.
        if ($template->documents()->exists()) {
            $template->update(['is_active' => false]);

            return back()->with('success', 'Template dinonaktifkan (masih dipakai oleh dokumen yang sudah dibuat).');
        }

        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }

    private function validateTemplate(Request $request): array
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string|max:2000',
            'category'            => 'required|in:' . implode(',', EvidenceTemplate::CATEGORIES),
            'body_html'           => 'required|string|max:200000',
            'orientation'         => 'nullable|in:portrait,landscape',
            'fields'              => 'nullable|array|max:20',
            'fields.*.key'        => 'required|string|max:60|regex:/^[A-Za-z0-9_]+$/',
            'fields.*.label'      => 'required|string|max:120',
            'fields.*.type'       => 'nullable|in:text,date,number',
            'fields.*.placeholder' => 'nullable|string|max:160',
        ]);

        $data['body_html']   = HtmlSanitizer::clean($data['body_html']);
        $data['orientation'] = $data['orientation'] ?? 'portrait';
        $data['fields']      = array_values($data['fields'] ?? []);

        return $data;
    }

    private function authorizeManaging(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['super_admin', 'approval', 'reviewer']), 403,
            'Hanya Super Admin, Manajer, atau Reviewer yang dapat mengelola template dokumen.');
    }

    private function authorizeReading(EvidenceTemplate $template): void
    {
        abort_unless(
            $template->organization_id === null || $template->organization_id === auth()->user()->organization_id,
            403,
        );
    }

    /** Template bawaan sistem tidak boleh diubah atau dihapus. */
    private function authorizeWriting(EvidenceTemplate $template): void
    {
        abort_if($template->is_system, 403, 'Template bawaan sistem tidak dapat diubah — duplikasikan terlebih dahulu.');
        abort_if($template->organization_id !== auth()->user()->organization_id, 403);
    }
}
