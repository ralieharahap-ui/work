<?php

namespace App\Agent\Execution;

use App\Agent\Data\ToolResult;
use App\Models\AgentTaskStep;

/**
 * Mengubah kegagalan menjadi tindakan perbaikan yang konkret.
 *
 * Contoh nyata: ketika berkas penjualan ternyata memakai kolom
 * "total_revenue" alih-alih "revenue", pemulih ini menyusun pemetaan kolom,
 * mengulang langkah dengan pemetaan tersebut, dan menyiapkan pelajaran agar
 * pekerjaan berikutnya langsung memakai pemetaan itu sejak awal.
 */
class RecoveryPlanner
{
    public function __construct(private readonly ErrorClassifier $classifier)
    {
    }

    public function plan(AgentTaskStep $step, ToolResult $result, string $errorClass): Recovery
    {
        $inputs = $step->inputs ?? [];

        return match ($errorClass) {
            'missing_column'      => $this->remapColumns($step, $result, $inputs),
            'not_found'           => $this->remapDataset($step, $result, $inputs),
            'missing_integration' => Recovery::requestAccess(
                (string) $result->error,
                (string) ($result->data['integration'] ?? 'microsoft365'),
                array_values(array_map('strval', (array) ($result->data['alternatives'] ?? []))),
            ),
            'disabled'    => Recovery::requestAccess((string) $result->error, 'whatsapp'),
            'uncertain_execution' => Recovery::humanReview((string) $result->error),
            'permission'  => Recovery::abort('Agent tidak memiliki izin untuk tindakan ini: ' . $result->error),
            'validation'  => Recovery::escalate('Input langkah tidak memenuhi syarat: ' . $result->error),
            'empty_source'=> Recovery::replan('Sumber data kosong; rencana perlu disesuaikan.'),
            'missing_data'=> Recovery::humanReview($this->missingDataMessage($result)),
            default       => $this->classifier->isRetryable($errorClass)
                ? Recovery::retry('Kegagalan sementara — dicoba ulang.')
                : Recovery::escalate((string) $result->error),
        };
    }

    /** @param array<string, mixed> $inputs */
    private function remapColumns(AgentTaskStep $step, ToolResult $result, array $inputs): Recovery
    {
        $missing   = (array) ($result->data['missing_columns'] ?? []);
        $available = (array) ($result->data['available_columns'] ?? []);
        $map       = [];

        foreach ($missing as $wanted) {
            $match = $this->bestMatch((string) $wanted, $available);

            if ($match !== null) {
                $map[(string) $wanted] = $match;
            }
        }

        if ($map === []) {
            return Recovery::escalate(
                'Kolom ' . implode(', ', array_map('strval', $missing))
                . ' tidak ada dan tidak ditemukan padanannya pada data.'
            );
        }

        $inputs['column_map'] = array_merge((array) ($inputs['column_map'] ?? []), $map);

        $dataset = (string) ($result->data['dataset'] ?? $inputs['dataset'] ?? 'sumber data');
        $family  = $this->datasetFamily($dataset);
        $pairs   = implode(', ', array_map(
            static fn ($wanted, $actual) => "{$wanted} → {$actual}",
            array_keys($map), $map,
        ));

        return Recovery::adjustInputs(
            "Memetakan kolom {$pairs} lalu mengulang langkah.",
            $inputs,
            [
                'scope'   => 'data',
                // Pelajaran diikat ke keluarga berkas (mis. penjualan-*-*.csv),
                // bukan ke satu berkas, karena berkas bulanan berganti nama
                // tiap periode sementara tata nama kolomnya tetap sama.
                'subject'        => $family,
                'trigger'        => "Membaca berkas data {$family}",
                'lesson'         => "Berkas {$family} memakai penamaan kolom berbeda: {$pairs}.",
                'recommendation' => "Sertakan column_map {$pairs} sejak langkah pertama saat membaca berkas {$family}.",
                'payload'        => [
                    'type'            => 'column_map',
                    'dataset'         => $dataset,
                    'dataset_pattern' => $family,
                    'column_map'      => $map,
                ],
                'evidence'       => ['available_columns' => $available, 'missing_columns' => $missing],
                'confidence'     => 0.7,
            ],
        );
    }

    /** @param array<string, mixed> $inputs */
    private function remapDataset(AgentTaskStep $step, ToolResult $result, array $inputs): Recovery
    {
        $available = (array) ($result->data['available_datasets'] ?? []);
        $wanted    = (string) ($inputs['dataset'] ?? '');

        if ($wanted === '' || $available === []) {
            return Recovery::escalate((string) $result->error);
        }

        $match = $this->bestMatch($wanted, $available, 0.45);

        if ($match === null) {
            return Recovery::escalate(
                "Berkas '{$wanted}' tidak ada. Yang tersedia: " . implode(', ', array_map('strval', $available)) . '.'
            );
        }

        $inputs['dataset'] = $match;

        return Recovery::adjustInputs(
            "Berkas '{$wanted}' tidak ada; memakai '{$match}' yang paling mendekati.",
            $inputs,
            [
                'scope'          => 'data',
                'subject'        => $match,
                'trigger'        => "Mencari berkas bernama {$wanted}",
                'lesson'         => "Nama berkas yang benar untuk '{$wanted}' adalah '{$match}'.",
                'recommendation' => "Gunakan '{$match}' sebagai nama berkas sumber.",
                'payload'        => ['type' => 'dataset_alias', 'alias' => $wanted, 'dataset' => $match],
                'evidence'       => ['available_datasets' => $available],
                'confidence'     => 0.65,
            ],
        );
    }

    /** Pesan kekurangan data dilengkapi pilihan yang tersedia bila ada. */
    private function missingDataMessage(ToolResult $result): string
    {
        $available = array_map('strval', (array) ($result->data['available_datasets'] ?? []));

        return $available === []
            ? (string) $result->error
            : $result->error . ' Berkas yang tersedia: ' . implode(', ', $available) . '.';
    }

    /**
     * Pola keluarga berkas: deretan angka diganti tanda bintang sehingga
     * 'penjualan-2026-07.csv' menjadi 'penjualan-*-*.csv' dan berlaku untuk
     * berkas bulan berikutnya.
     */
    private function datasetFamily(string $dataset): string
    {
        return preg_replace('/\d+/', '*', $dataset) ?: $dataset;
    }

    /**
     * Padanan terdekat: kandidat yang memuat/dimuat oleh nama yang dicari
     * lebih diutamakan, sisanya dinilai dari kemiripan teks.
     *
     * @param  array<int, mixed>  $candidates
     */
    private function bestMatch(string $wanted, array $candidates, float $threshold = 0.55): ?string
    {
        $wantedLower = mb_strtolower($wanted);
        $best        = null;
        $bestScore   = 0.0;

        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;
            $lower     = mb_strtolower($candidate);

            similar_text($wantedLower, $lower, $percent);
            $score = $percent / 100;

            if (str_contains($lower, $wantedLower) || str_contains($wantedLower, $lower)) {
                $score = max($score, 0.85);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $candidate;
            }
        }

        return $bestScore >= $threshold ? $best : null;
    }
}
