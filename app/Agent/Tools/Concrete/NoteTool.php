<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;
use Illuminate\Support\Str;

/**
 * Langkah penalaran tanpa efek samping: merangkum hasil langkah sebelumnya
 * menjadi catatan yang dapat dibaca manusia (dan diverifikasi).
 */
class NoteTool extends BaseTool
{
    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'agent.note',
            title: 'Catatan kerja',
            description: 'Merangkum data atau keputusan menjadi catatan ringkas. Tidak mengubah apa pun.',
            inputSchema: [
                'topic'  => ['type' => 'string', 'required' => true, 'description' => 'Judul catatan'],
                'source' => ['type' => 'array', 'description' => 'Keluaran langkah sebelumnya'],
                'points' => ['type' => 'array', 'description' => 'Butir tambahan yang ingin dicatat'],
            ],
            outputKeys: ['note', 'points'],
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $points = array_values(array_filter(array_map('strval', (array) ($input['points'] ?? []))));
        $source = (array) ($input['source'] ?? []);

        foreach ($this->summarizeSource($source) as $line) {
            $points[] = $line;
        }

        if ($points === []) {
            $points[] = 'Tujuan: ' . Str::limit($context->task->objective, 160);
        }

        $note = '### ' . $input['topic'] . "\n" . implode("\n", array_map(static fn ($p) => "- {$p}", $points));

        return ToolResult::success(
            ['note' => $note, 'points' => $points, 'topic' => $input['topic']],
            'Catatan tersusun dengan ' . count($points) . ' butir.',
        );
    }

    /** @return array<int, string> */
    private function summarizeSource(array $source): array
    {
        $lines = [];

        if (isset($source['tasks']) && is_array($source['tasks'])) {
            foreach (array_slice($source['tasks'], 0, 10) as $task) {
                $lines[] = sprintf(
                    '%s — %s (PIC: %s, tenggat: %s)',
                    $task['title'] ?? 'Tugas',
                    $task['status'] ?? '-',
                    $task['pic'] ?? 'belum ditentukan',
                    $task['deadline'] ?? 'tanpa tenggat',
                );
            }
        }

        if (isset($source['totals']) && is_array($source['totals'])) {
            foreach ($source['totals'] as $metric => $value) {
                $lines[] = 'Total ' . $metric . ': ' . (is_numeric($value) ? number_format((float) $value, 0, ',', '.') : (string) $value);
            }
        }

        if (isset($source['note'])) {
            $lines[] = Str::limit((string) $source['note'], 200);
        }

        return $lines;
    }
}
