<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Menyusun draf email. Tidak mengirim apa pun — pengiriman ada di tool terpisah. */
class EmailDraftTool extends BaseTool
{
    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'email.draft',
            title: 'Susun draf email',
            description: 'Menyusun draf email (penerima, subjek, isi) dari konteks pekerjaan.',
            inputSchema: [
                'to'      => ['type' => 'array', 'required' => true, 'description' => 'Daftar alamat penerima'],
                'subject' => ['type' => 'string'],
                'points'  => ['type' => 'array', 'description' => 'Poin yang harus disebut dalam email'],
                'source'  => ['type' => 'array', 'description' => 'Keluaran langkah sebelumnya'],
                'closing' => ['type' => 'string'],
            ],
            outputKeys: ['to', 'subject', 'body', 'attachment'],
            riskLevel: 'low',
            readOnly: false,
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $to = array_values(array_filter(
            array_map('trim', array_map('strval', (array) $input['to'])),
            static fn (string $address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if ($to === []) {
            return ToolResult::failure('Tidak ada alamat email penerima yang sah.', 'validation', [
                'hint' => 'Sertakan alamat email lengkap pada konteks task (email_to).',
            ]);
        }

        $source  = (array) ($input['source'] ?? []);
        $subject = trim((string) ($input['subject'] ?? '')) ?: (string) ($source['title'] ?? Str::limit($context->task->title, 60));
        $points  = array_values(array_filter(array_map('strval', (array) ($input['points'] ?? []))));

        if (! empty($source['totals'])) {
            foreach ($source['totals'] as $metric => $value) {
                $points[] = 'Total ' . $metric . ': ' . number_format((float) $value, 0, ',', '.');
            }
        }

        if ($points === []) {
            $points[] = Str::limit($context->task->objective, 200);
        }

        $body = "Yth. Bapak/Ibu,\n\n"
            . "Berikut kami sampaikan " . Str::lower($subject) . ":\n\n"
            . implode("\n", array_map(static fn (string $p) => '- ' . $p, $points)) . "\n\n"
            . (isset($source['filename']) ? "Dokumen terlampir: {$source['filename']}\n\n" : '')
            . (trim((string) ($input['closing'] ?? '')) ?: "Demikian kami sampaikan, terima kasih.") . "\n\n"
            . "Hormat kami,\n" . ($context->user?->name ?? config('app.name'));

        $path = $context->workspacePath('email/draft-' . now()->format('Ymd-His') . '.txt');
        Storage::disk('local')->put($path, "To: " . implode(', ', $to) . "\nSubject: {$subject}\n\n{$body}");

        return ToolResult::success([
            'to'         => $to,
            'subject'    => $subject,
            'body'       => $body,
            'attachment' => $source['path'] ?? null,
            'draft_path' => $path,
        ], 'Draf email untuk ' . implode(', ', $to) . " tersusun dengan subjek \"{$subject}\".");
    }
}
