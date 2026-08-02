<?php

namespace App\Services;

use App\Models\EvidenceDocument;
use App\Models\EvidenceTemplate;
use App\Models\Task;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyiapkan, menyimpan, dan membekukan dokumen bukti (evidence) sebuah task.
 *
 * Alur lengkapnya: template → dokumen draft (bisa diedit & dicetak) →
 * ditandatangani PIC → otomatis dibekukan menjadi PDF → dipakai sebagai syarat
 * penutupan task.
 */
class EvidenceDocumentService
{
    /** Penanda yang tersedia di dalam template, ditampilkan juga di antarmuka. */
    public const PLACEHOLDERS = [
        '{{doc.number}}'      => 'Nomor dokumen',
        '{{doc.title}}'       => 'Judul dokumen',
        '{{task.title}}'      => 'Judul task',
        '{{task.description}}' => 'Deskripsi task',
        '{{task.category}}'   => 'Kategori task',
        '{{task.status}}'     => 'Status task',
        '{{task.priority}}'   => 'Prioritas task',
        '{{task.deadline}}'   => 'Tenggat task',
        '{{task.checklist}}'  => 'Daftar sub-task (checklist)',
        '{{project.title}}'   => 'Nama proyek terkait',
        '{{pic.name}}'        => 'Nama PIC',
        '{{pic.email}}'       => 'Email PIC',
        '{{division.name}}'   => 'Divisi',
        '{{creator.name}}'    => 'Pembuat task',
        '{{author.name}}'     => 'Pembuat dokumen',
        '{{org.name}}'        => 'Nama perusahaan',
        '{{today}}'           => 'Tanggal hari ini (05 Agu 2026)',
        '{{today.long}}'      => 'Tanggal hari ini (5 Agustus 2026)',
    ];

    /** Buat dokumen draft baru untuk sebuah task, dari template atau kosong. */
    public function createForTask(Task $task, ?EvidenceTemplate $template, User $author, array $overrides = []): EvidenceDocument
    {
        $task->loadMissing(['pic', 'project', 'division', 'creator', 'checklistItems']);

        $number = $overrides['number'] ?? $this->nextNumber($task, $template);
        $title  = $overrides['title'] ?? ($template?->name ?? 'Dokumen Bukti Penyelesaian');

        $body = $template?->body_html ?? $this->blankBody();

        return EvidenceDocument::create([
            'organization_id' => $task->organization_id,
            'task_id'         => $task->id,
            'template_id'     => $template?->id,
            'created_by'      => $author->id,
            'number'          => $number,
            'title'           => $title,
            'content_html'    => HtmlSanitizer::clean($this->fillPlaceholders($body, $task, $author, $number, $title)),
            'data'            => $this->defaultFieldValues($template),
            'orientation'     => $template?->orientation ?? 'portrait',
            'status'          => EvidenceDocument::STATUS_DRAFT,
        ]);
    }

    /** Ganti penanda {{...}} di dalam template dengan data task yang sebenarnya. */
    public function fillPlaceholders(string $html, Task $task, User $author, ?string $number, ?string $title): string
    {
        $today = Carbon::today()->locale('id');

        $checklist = $task->checklistItems
            ->map(fn ($item) => '<li>' . e($item->text) . ($item->is_completed ? ' — <em>selesai</em>' : '') . '</li>')
            ->implode('');

        $values = [
            '{{doc.number}}'       => e((string) $number),
            '{{doc.title}}'        => e((string) $title),
            '{{task.title}}'       => e((string) $task->title),
            '{{task.description}}' => nl2br(e((string) $task->description)),
            '{{task.category}}'    => e((string) $task->category),
            '{{task.status}}'      => e((string) $task->status),
            '{{task.priority}}'    => e((string) $task->priority),
            '{{task.deadline}}'    => $task->deadline ? e($task->deadline->copy()->locale('id')->translatedFormat('j F Y')) : '-',
            '{{task.checklist}}'   => $checklist !== '' ? '<ul>' . $checklist . '</ul>' : '<p><em>Tidak ada sub-task.</em></p>',
            '{{project.title}}'    => e((string) ($task->project?->title ?? '-')),
            '{{pic.name}}'         => e((string) ($task->pic?->name ?? '-')),
            '{{pic.email}}'        => e((string) ($task->pic?->email ?? '-')),
            '{{division.name}}'    => e((string) ($task->division?->name ?? $task->pic?->division?->name ?? '-')),
            '{{creator.name}}'     => e((string) ($task->creator?->name ?? '-')),
            '{{author.name}}'      => e($author->name),
            '{{org.name}}'         => e((string) config('app.name', 'PT Geosys Energi Prima')),
            '{{today}}'            => e($today->translatedFormat('d M Y')),
            '{{today.long}}'       => e($today->translatedFormat('j F Y')),
        ];

        return strtr($html, $values);
    }

    /**
     * Bekukan dokumen: simpan tanda tangan, hasilkan PDF, dan tandai selesai.
     *
     * @param  array{signer_name:string, signer_position?:?string, signature_place?:?string, signature:string}  $payload
     */
    public function sign(EvidenceDocument $document, User $signer, array $payload): EvidenceDocument
    {
        if ($document->is_signed) {
            throw new RuntimeException('Dokumen ini sudah ditandatangani.');
        }

        $signaturePath = $this->storeSignature($document, $payload['signature']);

        $document->fill([
            'signature_path'  => $signaturePath,
            'signer_id'       => $signer->id,
            'signer_name'     => $payload['signer_name'],
            'signer_position' => $payload['signer_position'] ?? null,
            'signature_place' => $payload['signature_place'] ?? null,
            'signed_at'       => now(),
            'status'          => EvidenceDocument::STATUS_SIGNED,
        ]);

        [$pdfPath, $pdfName] = $this->renderPdf($document);

        $document->fill(['pdf_path' => $pdfPath, 'pdf_original_name' => $pdfName])->save();

        return $document->refresh();
    }

    /** Hasilkan berkas PDF dari dokumen dan simpan ke disk publik. */
    public function renderPdf(EvidenceDocument $document): array
    {
        $document->loadMissing(['task.pic', 'task.project', 'task.creator', 'template', 'signer']);

        $pdf = Pdf::loadView('evidence.document', [
            'document'  => $document,
            'task'      => $document->task,
            'signature' => $this->signatureDataUri($document),
            'logo'      => $this->logoDataUri(),
            'forPdf'    => true,
        ])
            // Bawaan dompdf adalah media "screen"; tanpa ini, tiruan lembar A4
            // yang hanya untuk pratinjau di peramban ikut diterapkan sehingga
            // isi dokumen meluber melewati batas halaman PDF.
            ->setOption('defaultMediaType', 'print')
            // Dokumen tidak boleh menarik berkas dari luar server saat dirender.
            ->setOption('isRemoteEnabled', false)
            ->setPaper('a4', $document->orientation === 'landscape' ? 'landscape' : 'portrait');

        $name = Str::slug(($document->number ? $document->number . '-' : '') . $document->title) ?: 'dokumen-bukti';
        $name = Str::limit($name, 80, '') . '.pdf';
        $path = "task-evidence/{$document->task_id}/{$document->id}/{$name}";

        Storage::disk('public')->put($path, $pdf->output());

        return [$path, $name];
    }

    /** Data URI tanda tangan untuk disematkan ke HTML cetak maupun PDF. */
    public function signatureDataUri(EvidenceDocument $document): ?string
    {
        if (! $document->signature_path || ! Storage::disk('public')->exists($document->signature_path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($document->signature_path));
    }

    public function logoDataUri(): ?string
    {
        $path = public_path('images/logo-gep.png');

        return is_readable($path) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($path)) : null;
    }

    /** Nomor dokumen berurutan per task, mis. BA/OPS/2026/08/0003. */
    public function nextNumber(Task $task, ?EvidenceTemplate $template): string
    {
        $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $template?->code ?? $template?->name ?? 'DOC') ?: 'DOC', 0, 3));
        $now    = Carbon::now();

        $sequence = EvidenceDocument::where('organization_id', $task->organization_id)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count() + 1;

        return sprintf('%s/%s/%s/%04d', $prefix, $now->format('Y'), $now->format('m'), $sequence);
    }

    private function storeSignature(EvidenceDocument $document, string $dataUrl): string
    {
        if (! preg_match('#^data:image/png;base64,(?<data>[A-Za-z0-9+/=\s]+)$#', trim($dataUrl), $matches)) {
            throw new RuntimeException('Format tanda tangan tidak valid (harus gambar PNG).');
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $matches['data']), true);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Tanda tangan tidak dapat dibaca.');
        }

        if (strlen($binary) > 2 * 1024 * 1024) {
            throw new RuntimeException('Berkas tanda tangan terlalu besar (maksimal 2MB).');
        }

        // Pastikan isinya benar-benar PNG, bukan berkas lain yang diberi label PNG.
        if (! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('Tanda tangan bukan berkas PNG yang sah.');
        }

        $path = "task-evidence/{$document->task_id}/{$document->id}/signature.png";
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function defaultFieldValues(?EvidenceTemplate $template): array
    {
        $values = [];

        foreach ($template?->fields ?? [] as $field) {
            if (is_array($field) && ! empty($field['key'])) {
                $values[$field['key']] = $field['default'] ?? '';
            }
        }

        return $values;
    }

    private function blankBody(): string
    {
        return <<<'HTML'
<h2 style="text-align: center">DOKUMEN BUKTI PENYELESAIAN</h2>
<p>Nomor: {{doc.number}}</p>
<p>Pada hari ini, {{today.long}}, telah diselesaikan pekerjaan dengan rincian berikut:</p>
<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">
  <tr><td style="width: 30%">Uraian Pekerjaan</td><td>{{task.title}}</td></tr>
  <tr><td>Penanggung Jawab</td><td>{{pic.name}} — {{division.name}}</td></tr>
  <tr><td>Tenggat</td><td>{{task.deadline}}</td></tr>
</table>
<p>Hasil pekerjaan:</p>
<p>{{task.description}}</p>
HTML;
    }
}
