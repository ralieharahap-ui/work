<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;

/** Menghitung total, rata-rata, dan pengelompokan dari baris data. */
class DataAnalyzeTool extends BaseTool
{
    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'data.analyze',
            title: 'Hitung KPI',
            description: 'Menjumlah & mengelompokkan metrik numerik dari kumpulan baris data.',
            inputSchema: [
                'source'   => ['type' => 'array', 'required' => true, 'description' => 'Keluaran langkah pembacaan data'],
                'metrics'  => ['type' => 'array', 'required' => true, 'description' => 'Kolom numerik yang dihitung'],
                'group_by' => ['type' => 'string', 'description' => 'Kolom pengelompokan'],
            ],
            outputKeys: ['totals', 'groups', 'row_count', 'averages'],
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $rows = $this->rowsFrom($input['source']);

        if ($rows === []) {
            return ToolResult::failure('Tidak ada baris data yang bisa dihitung.', 'empty_source');
        }

        $metrics  = array_values(array_filter(array_map('strval', (array) $input['metrics'])));
        $groupBy  = $input['group_by'] ?? null;
        $columns  = array_keys($rows[0]);
        $missing  = array_values(array_diff($metrics, $columns));

        if ($missing !== []) {
            return ToolResult::failure(
                'Metrik tidak ditemukan pada data: ' . implode(', ', $missing) . '.',
                'missing_column',
                ['missing_columns' => $missing, 'available_columns' => $columns],
            );
        }

        if ($groupBy && ! in_array($groupBy, $columns, true)) {
            return ToolResult::failure(
                "Kolom pengelompokan '{$groupBy}' tidak ada pada data.",
                'missing_column',
                ['missing_columns' => [$groupBy], 'available_columns' => $columns],
            );
        }

        $totals = array_fill_keys($metrics, 0.0);
        $groups = [];

        foreach ($rows as $row) {
            $key = $groupBy ? (string) ($row[$groupBy] ?? '(kosong)') : '(semua)';
            $groups[$key] ??= ['key' => $key, 'rows' => 0] + array_fill_keys($metrics, 0.0);
            $groups[$key]['rows']++;

            foreach ($metrics as $metric) {
                $value = $this->numeric($row[$metric] ?? 0);
                $totals[$metric] += $value;
                $groups[$key][$metric] += $value;
            }
        }

        $rowCount = count($rows);
        $averages = array_map(static fn (float $sum) => round($sum / max(1, $rowCount), 2), $totals);

        // Urutkan kelompok menurut metrik pertama agar laporan langsung informatif.
        $primary = $metrics[0];
        usort($groups, static fn (array $a, array $b) => $b[$primary] <=> $a[$primary]);

        return ToolResult::success([
            'totals'    => array_map(static fn (float $v) => round($v, 2), $totals),
            'averages'  => $averages,
            'groups'    => array_values($groups),
            'group_by'  => $groupBy,
            'row_count' => $rowCount,
            'top'       => $groups[0]['key'] ?? null,
        ], sprintf(
            'Dihitung %d baris; total %s.',
            $rowCount,
            implode(', ', array_map(
                static fn ($m, $v) => $m . ' = ' . number_format((float) $v, 0, ',', '.'),
                array_keys($totals),
                $totals,
            )),
        ));
    }
}
