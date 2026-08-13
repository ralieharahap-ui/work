<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;

/** Membandingkan dua kumpulan baris berdasarkan kolom kunci. */
class DataCompareTool extends BaseTool
{
    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'data.compare',
            title: 'Bandingkan data',
            description: 'Menyejajarkan dua sumber data pada kolom kunci lalu mencatat selisihnya.',
            inputSchema: [
                'left'    => ['type' => 'array', 'required' => true],
                'right'   => ['type' => 'array', 'required' => true],
                'key'     => ['type' => 'string', 'required' => true, 'description' => 'Kolom kunci penyejajar'],
                'compare' => ['type' => 'array', 'description' => 'Kolom yang dibandingkan; kosong = semua kolom sama-sama ada'],
            ],
            outputKeys: ['differences', 'only_left', 'only_right', 'summary'],
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $left  = $this->rowsFrom($input['left']);
        $right = $this->rowsFrom($input['right']);
        $key   = (string) $input['key'];

        if ($left === [] || $right === []) {
            return ToolResult::failure('Salah satu sumber data kosong.', 'empty_source');
        }

        foreach (['kiri' => $left, 'kanan' => $right] as $label => $rows) {
            if (! array_key_exists($key, $rows[0])) {
                return ToolResult::failure(
                    "Kolom kunci '{$key}' tidak ada pada sumber {$label}.",
                    'missing_column',
                    ['missing_columns' => [$key], 'available_columns' => array_keys($rows[0])],
                );
            }
        }

        $compare = array_values(array_filter(array_map('strval', (array) ($input['compare'] ?? []))));

        if ($compare === []) {
            $compare = array_values(array_diff(
                array_intersect(array_keys($left[0]), array_keys($right[0])),
                [$key],
            ));
        }

        $index = static function (array $rows) use ($key) {
            $out = [];
            foreach ($rows as $row) {
                $out[(string) $row[$key]] = $row;
            }

            return $out;
        };

        $leftIndex  = $index($left);
        $rightIndex = $index($right);

        $differences = [];
        foreach ($leftIndex as $id => $row) {
            if (! isset($rightIndex[$id])) {
                continue;
            }

            foreach ($compare as $column) {
                $a = $row[$column] ?? null;
                $b = $rightIndex[$id][$column] ?? null;

                if ((string) $a !== (string) $b) {
                    $delta = (is_numeric($a) || is_numeric($b))
                        ? round($this->numeric($b) - $this->numeric($a), 2)
                        : null;

                    $differences[] = [
                        'key' => (string) $id, 'column' => $column,
                        'left' => $a, 'right' => $b, 'delta' => $delta,
                    ];
                }
            }
        }

        $onlyLeft  = array_values(array_diff(array_keys($leftIndex), array_keys($rightIndex)));
        $onlyRight = array_values(array_diff(array_keys($rightIndex), array_keys($leftIndex)));

        return ToolResult::success([
            'differences' => $differences,
            'only_left'   => $onlyLeft,
            'only_right'  => $onlyRight,
            'summary'     => [
                'compared_columns' => $compare,
                'matched_keys'     => count(array_intersect(array_keys($leftIndex), array_keys($rightIndex))),
                'difference_count' => count($differences),
                'only_left_count'  => count($onlyLeft),
                'only_right_count' => count($onlyRight),
            ],
        ], sprintf(
            '%d selisih nilai, %d baris hanya di kiri, %d baris hanya di kanan.',
            count($differences), count($onlyLeft), count($onlyRight),
        ));
    }
}
