<?php

namespace App\Agent\Tools;

use App\Agent\Contracts\Tool;
use App\Agent\Data\ToolDefinition;
use InvalidArgumentException;

/**
 * Kerangka umum tool: validasi input berdasarkan skema yang dideklarasikan
 * sendiri oleh tool, plus utilitas kecil yang sering dipakai.
 */
abstract class BaseTool implements Tool
{
    public function name(): string
    {
        return $this->describe()->name;
    }

    abstract public function describe(): ToolDefinition;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $schema = $this->describe()->inputSchema;
        $clean  = [];

        foreach ($schema as $field => $rules) {
            $value    = $input[$field] ?? null;
            $required = (bool) ($rules['required'] ?? false);

            if ($value === null || $value === '') {
                if ($required && ! array_key_exists('default', $rules)) {
                    throw new InvalidArgumentException("Input '{$field}' wajib diisi untuk tool {$this->describe()->name}.");
                }

                if (array_key_exists('default', $rules)) {
                    $clean[$field] = $rules['default'];
                }

                continue;
            }

            $clean[$field] = $this->cast($field, $value, (string) ($rules['type'] ?? 'string'));

            if (! empty($rules['enum']) && ! in_array($clean[$field], $rules['enum'], true)) {
                throw new InvalidArgumentException(
                    "Nilai '{$field}' harus salah satu dari: " . implode(', ', $rules['enum']) . '.'
                );
            }
        }

        return $clean;
    }

    private function cast(string $field, mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'array'  => is_array($value) ? $value : [$value],
            'string' => is_scalar($value) ? (string) $value
                : throw new InvalidArgumentException("Input '{$field}' harus berupa teks."),
            default  => $value,
        };
    }

    /** Ambil daftar baris dari keluaran langkah sebelumnya. */
    protected function rowsFrom(mixed $source): array
    {
        if (! is_array($source)) {
            return [];
        }

        $rows = $source['rows'] ?? $source;

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    protected function numeric(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Toleransi format angka Indonesia: "1.250.000,50" dan "Rp 1.250.000".
        $clean = preg_replace('/[^0-9,\.\-]/', '', (string) $value) ?? '';

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
