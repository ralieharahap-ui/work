<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Membaca berkas data (CSV/TSV) dari ruang kerja agent.
 *
 * Ketika kolom yang diminta tidak ada, tool tidak sekadar gagal: ia
 * mengembalikan daftar kolom yang tersedia sebagai bahan pemulihan, sehingga
 * agent dapat memetakan nama kolom alternatif lalu mencatatnya sebagai
 * pelajaran untuk task berikutnya.
 */
class SpreadsheetReadTool extends BaseTool
{
    private const MAX_ROWS = 2000;

    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'spreadsheet.read',
            title: 'Baca berkas data',
            description: 'Membaca berkas CSV pada folder data agent dan mengembalikan barisnya.',
            inputSchema: [
                'dataset'          => ['type' => 'string', 'required' => true, 'description' => 'Nama berkas, mis. penjualan-2026-07.csv'],
                'required_columns' => ['type' => 'array', 'description' => 'Kolom yang wajib ada'],
                'column_map'       => ['type' => 'array', 'description' => 'Pemetaan nama kolom yang diminta → nama kolom asli'],
                'limit'            => ['type' => 'int', 'default' => 500],
            ],
            outputKeys: ['rows', 'columns', 'row_count', 'dataset'],
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $dataset = $this->safeName((string) $input['dataset']);

        if ($dataset === null) {
            return ToolResult::failure("Nama berkas '{$input['dataset']}' tidak diizinkan.", 'validation');
        }

        $disk = Storage::disk('local');
        $path = $this->locate($disk, $dataset, $context);

        if ($path === null) {
            return ToolResult::failure(
                "Berkas data '{$dataset}' tidak ditemukan.",
                'not_found',
                ['available_datasets' => $this->availableDatasets($disk, $context)],
            );
        }

        $rows = $this->parse($disk->path($path), (int) ($input['limit'] ?? 500));

        if ($rows === []) {
            return ToolResult::failure("Berkas '{$dataset}' kosong atau tidak memiliki baris data.", 'empty_source');
        }

        $columns   = array_keys($rows[0]);
        $columnMap = $this->normalizeMap((array) ($input['column_map'] ?? []));
        $required  = array_values(array_filter(array_map('strval', (array) ($input['required_columns'] ?? []))));

        // Terapkan pemetaan kolom (biasanya berasal dari pelajaran task sebelumnya).
        if ($columnMap !== []) {
            $rows = $this->applyMap($rows, $columnMap);
            $columns = array_keys($rows[0]);
        }

        $missing = array_values(array_diff($required, array_keys($rows[0])));

        if ($missing !== []) {
            return ToolResult::failure(
                'Kolom berikut tidak ada pada berkas: ' . implode(', ', $missing) . '.',
                'missing_column',
                [
                    'dataset'            => $dataset,
                    'missing_columns'    => $missing,
                    'available_columns'  => $columns,
                ],
            );
        }

        return ToolResult::success([
            'dataset'            => $dataset,
            'rows'               => $rows,
            'columns'            => $columns,
            'row_count'          => count($rows),
            'applied_column_map' => $columnMap,
        ], sprintf('Terbaca %d baris dari %s (kolom: %s).', count($rows), $dataset, implode(', ', $columns)));
    }

    private function safeName(string $name): ?string
    {
        $name = trim($name);

        // Sandbox: hanya nama berkas polos, tidak boleh menembus direktori lain.
        if ($name === '' || str_contains($name, '..') || Str::contains($name, ['/', '\\', "\0"])) {
            return null;
        }

        return $name;
    }

    private function locate($disk, string $dataset, ToolContext $context): ?string
    {
        $base = trim((string) config('agent.workspace', 'agent'), '/');

        foreach ([$context->workspacePath('datasets/' . $dataset), $base . '/datasets/' . $dataset] as $candidate) {
            if ($disk->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function availableDatasets($disk, ToolContext $context): array
    {
        $base  = trim((string) config('agent.workspace', 'agent'), '/');
        $files = array_merge(
            $disk->files($base . '/datasets'),
            $disk->files($context->workspacePath('datasets')),
        );

        return array_values(array_unique(array_map('basename', $files)));
    }

    /** @return array<int, array<string, string>> */
    private function parse(string $absolutePath, int $limit): array
    {
        $handle = @fopen($absolutePath, 'r');

        if ($handle === false) {
            return [];
        }

        $delimiter = str_ends_with($absolutePath, '.tsv') ? "\t" : ',';
        $header    = null;
        $rows      = [];
        $limit     = max(1, min($limit, self::MAX_ROWS));

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($line === [null] || $line === false) {
                continue;
            }

            if ($header === null) {
                $header = array_map(
                    static fn ($h) => Str::of((string) $h)->trim()->replace("\u{FEFF}", '')->lower()->replace(' ', '_')->toString(),
                    $line,
                );

                continue;
            }

            if (count($rows) >= $limit) {
                break;
            }

            $row = [];
            foreach ($header as $i => $column) {
                $row[$column] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private function normalizeMap(array $map): array
    {
        $clean = [];

        foreach ($map as $wanted => $actual) {
            if (is_string($wanted) && is_string($actual) && $wanted !== '' && $actual !== '') {
                $clean[$wanted] = $actual;
            }
        }

        return $clean;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, string>  $map
     * @return array<int, array<string, string>>
     */
    private function applyMap(array $rows, array $map): array
    {
        return array_map(static function (array $row) use ($map) {
            foreach ($map as $wanted => $actual) {
                if (! array_key_exists($wanted, $row) && array_key_exists($actual, $row)) {
                    $row[$wanted] = $row[$actual];
                }
            }

            return $row;
        }, $rows);
    }
}
