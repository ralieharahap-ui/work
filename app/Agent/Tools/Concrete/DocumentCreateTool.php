<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyusun dokumen kerja (Markdown, HTML, atau PDF) di ruang kerja task.
 * Berkasnya nyata dan dapat diunduh dari dasbor sebagai bukti hasil kerja.
 */
class DocumentCreateTool extends BaseTool
{
    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'document.create',
            title: 'Buat dokumen',
            description: 'Menyusun dokumen dari data langkah sebelumnya dan menyimpannya sebagai berkas.',
            inputSchema: [
                'title'    => ['type' => 'string', 'required' => true],
                'template' => ['type' => 'string', 'default' => 'umum', 'enum' => ['umum', 'laporan', 'rekonsiliasi', 'memo']],
                'source'   => ['type' => 'array'],
                'sections' => ['type' => 'array', 'description' => 'Bagian tambahan berupa {judul: isi}'],
                'format'   => ['type' => 'string', 'default' => 'markdown', 'enum' => ['markdown', 'html', 'pdf']],
            ],
            outputKeys: ['path', 'filename', 'title', 'preview', 'bytes'],
            riskLevel: 'low',
            readOnly: false,
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $title    = (string) $input['title'];
        $format   = (string) ($input['format'] ?? 'markdown');
        $body     = $this->compose($title, (string) ($input['template'] ?? 'umum'), (array) ($input['source'] ?? []), (array) ($input['sections'] ?? []));
        $filename = Str::slug($title) . '-' . now()->format('Ymd-His') . '.' . ($format === 'pdf' ? 'pdf' : ($format === 'html' ? 'html' : 'md'));
        $path     = $context->workspacePath('documents/' . $filename);

        try {
            $contents = match ($format) {
                'pdf'   => Pdf::loadHTML($this->html($title, $body))->setPaper('a4')->setOption('defaultMediaType', 'print')->output(),
                'html'  => $this->html($title, $body),
                default => $body,
            };

            Storage::disk('local')->put($path, $contents);
        } catch (Throwable $e) {
            return ToolResult::failure('Gagal menyimpan dokumen: ' . $e->getMessage(), 'io_error');
        }

        return ToolResult::success([
            'path'     => $path,
            'filename' => $filename,
            'title'    => $title,
            'format'   => $format,
            'bytes'    => Storage::disk('local')->size($path),
            'preview'  => Str::limit(strip_tags($body), 600),
        ], "Dokumen \"{$title}\" tersimpan sebagai {$filename}.", [
            ['path' => $path, 'name' => $filename, 'mime' => $format === 'pdf' ? 'application/pdf' : 'text/plain'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $sections
     */
    private function compose(string $title, string $template, array $source, array $sections): string
    {
        $lines = [
            '# ' . $title,
            '',
            '_Disusun otomatis oleh ' . config('agent.name', 'Asisten Kantor') . ' pada '
                . Carbon::now()->locale('id')->translatedFormat('d F Y H:i') . '_',
            '',
        ];

        if ($template === 'laporan' && isset($source['totals'])) {
            $lines[] = '## Ringkasan Angka';
            $lines[] = '';
            $lines[] = '| Metrik | Total | Rata-rata |';
            $lines[] = '| --- | ---: | ---: |';

            foreach ((array) $source['totals'] as $metric => $value) {
                $average = $source['averages'][$metric] ?? null;
                $lines[] = sprintf(
                    '| %s | %s | %s |',
                    $metric,
                    number_format((float) $value, 2, ',', '.'),
                    $average === null ? '-' : number_format((float) $average, 2, ',', '.'),
                );
            }

            $lines[] = '';

            if (! empty($source['groups'])) {
                $groupBy = (string) ($source['group_by'] ?? 'kelompok');
                $lines[] = '## Rincian per ' . $groupBy;
                $lines[] = '';
                $metrics = array_keys((array) $source['totals']);
                $lines[] = '| ' . $groupBy . ' | ' . implode(' | ', $metrics) . ' | baris |';
                $lines[] = '| --- |' . str_repeat(' ---: |', count($metrics) + 1);

                foreach (array_slice((array) $source['groups'], 0, 25) as $group) {
                    $cells = array_map(
                        static fn (string $m) => number_format((float) ($group[$m] ?? 0), 2, ',', '.'),
                        $metrics,
                    );
                    $lines[] = '| ' . $group['key'] . ' | ' . implode(' | ', $cells) . ' | ' . ($group['rows'] ?? 0) . ' |';
                }

                $lines[] = '';
            }
        }

        if ($template === 'rekonsiliasi' && isset($source['differences'])) {
            $summary = (array) ($source['summary'] ?? []);
            $lines[] = '## Ringkasan';
            $lines[] = '';
            $lines[] = '- Baris cocok: ' . ($summary['matched_keys'] ?? 0);
            $lines[] = '- Selisih nilai: ' . ($summary['difference_count'] ?? 0);
            $lines[] = '- Hanya di sumber pertama: ' . ($summary['only_left_count'] ?? 0);
            $lines[] = '- Hanya di sumber pembanding: ' . ($summary['only_right_count'] ?? 0);
            $lines[] = '';
            $lines[] = '## Daftar Selisih';
            $lines[] = '';
            $lines[] = '| Kunci | Kolom | Sumber 1 | Sumber 2 | Selisih |';
            $lines[] = '| --- | --- | ---: | ---: | ---: |';

            foreach (array_slice((array) $source['differences'], 0, 50) as $diff) {
                $lines[] = sprintf('| %s | %s | %s | %s | %s |',
                    $diff['key'], $diff['column'], $diff['left'], $diff['right'], $diff['delta'] ?? '-');
            }

            $lines[] = '';
        }

        if (! empty($source['note'])) {
            $lines[] = (string) $source['note'];
            $lines[] = '';
        }

        foreach ($sections as $heading => $content) {
            $lines[] = '## ' . $heading;
            $lines[] = '';
            $lines[] = is_array($content) ? implode("\n", array_map(static fn ($c) => "- {$c}", $content)) : (string) $content;
            $lines[] = '';
        }

        // Jaminan minimal: dokumen selalu memuat baris "Total" agar dapat diverifikasi.
        if (! str_contains(implode("\n", $lines), 'Total')) {
            $lines[] = '## Total';
            $lines[] = '';
            $lines[] = 'Tidak ada angka agregat pada pekerjaan ini.';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function html(string $title, string $markdown): string
    {
        $rows = '';

        foreach (explode("\n", $markdown) as $line) {
            $rows .= match (true) {
                str_starts_with($line, '# ')  => '<h1>' . e(substr($line, 2)) . '</h1>',
                str_starts_with($line, '## ') => '<h2>' . e(substr($line, 3)) . '</h2>',
                str_starts_with($line, '| ')  => '<div class="row">' . e($line) . '</div>',
                str_starts_with($line, '- ')  => '<li>' . e(substr($line, 2)) . '</li>',
                trim($line) === ''            => '',
                default                       => '<p>' . e($line) . '</p>',
            };
        }

        return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>' . e($title) . '</title>'
            . '<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111}h1{font-size:18px}'
            . 'h2{font-size:13px;margin-top:14px}.row{font-family:DejaVu Sans Mono,monospace;font-size:10px}</style>'
            . '</head><body>' . $rows . '</body></html>';
    }
}
