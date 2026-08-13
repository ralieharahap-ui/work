<?php

namespace App\Agent\Verification;

use App\Agent\Data\Verification;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pemeriksa hasil. "Tool selesai" tidak sama dengan "pekerjaan benar", maka
 * setiap langkah dan seluruh pekerjaan diuji terhadap kriteria yang bisa
 * dibuktikan: berkas benar-benar ada, angka benar-benar cocok, kolom yang
 * diminta benar-benar terisi.
 */
class Verifier
{
    /** Memeriksa satu langkah terhadap success_criteria-nya. */
    public function verifyStep(AgentTaskStep $step): Verification
    {
        $output   = (array) ($step->output ?? []);
        $criteria = (array) ($step->success_criteria ?? []);
        $checks   = [];

        if ($criteria === []) {
            $criteria = ['output_not_empty'];
        }

        foreach ($criteria as $criterion) {
            $checks[] = $this->evaluate((string) $criterion, $output);
        }

        return Verification::fromChecks($checks, "Pemeriksaan langkah {$step->step_key}.");
    }

    /**
     * Memeriksa seluruh pekerjaan: kelengkapan langkah, keberadaan berkas
     * hasil, kesesuaian dengan batasan, dan kecocokan angka antar langkah.
     */
    public function verifyTask(AgentTask $task): Verification
    {
        $steps  = $task->steps()->get();
        $checks = [];

        $unfinished = $steps->filter(fn (AgentTaskStep $s) => ! $s->isDone());
        $checks[] = [
            'criterion' => 'seluruh_langkah_selesai',
            'passed'    => $unfinished->isEmpty(),
            'detail'    => $unfinished->isEmpty()
                ? $steps->count() . ' langkah tuntas'
                : 'belum tuntas: ' . $unfinished->pluck('step_key')->implode(', '),
        ];

        $deliverables = $this->deliverables($steps);
        if ($deliverables !== []) {
            $missing = array_values(array_filter(
                $deliverables,
                static fn (array $file) => ! Storage::disk('local')->exists($file['path'])
                    || Storage::disk('local')->size($file['path']) === 0,
            ));

            $checks[] = [
                'criterion' => 'berkas_hasil_tersedia',
                'passed'    => $missing === [],
                'detail'    => $missing === []
                    ? count($deliverables) . ' berkas hasil tersimpan'
                    : 'berkas hilang/kosong: ' . implode(', ', array_column($missing, 'name')),
            ];
        }

        foreach ($this->crossCheckTotals($steps) as $check) {
            $checks[] = $check;
        }

        foreach ((array) ($task->constraints ?? []) as $constraint => $value) {
            $checks[] = $this->evaluateConstraint((string) $constraint, $value, $steps, $task);
        }

        $failedSteps = $steps->filter(fn (AgentTaskStep $s) => $s->status === 'failed');
        if ($failedSteps->isNotEmpty()) {
            $checks[] = [
                'criterion' => 'tidak_ada_langkah_gagal',
                'passed'    => false,
                'detail'    => 'gagal: ' . $failedSteps->pluck('step_key')->implode(', '),
            ];
        }

        return Verification::fromChecks($checks, 'Pemeriksaan akhir pekerjaan.');
    }

    /**
     * Konsistensi angka: bila sebuah langkah menghasilkan total dan langkah
     * lain menuangkannya ke dokumen, angka tersebut harus benar-benar muncul
     * di dokumen — bukan sekadar dokumen berhasil dibuat.
     *
     * @param  \Illuminate\Support\Collection<int, AgentTaskStep>  $steps
     * @return array<int, array{criterion: string, passed: bool, detail: string}>
     */
    private function crossCheckTotals($steps): array
    {
        $totalsStep = $steps->last(fn (AgentTaskStep $s) => ! empty($s->output['totals']));
        $documents  = $steps->filter(fn (AgentTaskStep $s) => ! empty($s->output['path']));

        if (! $totalsStep || $documents->isEmpty()) {
            return [];
        }

        $checks = [];

        foreach ($documents as $document) {
            $path = (string) $document->output['path'];

            if (! Storage::disk('local')->exists($path) || str_ends_with($path, '.pdf')) {
                continue; // PDF diperiksa lewat kriteria keberadaan berkas.
            }

            $contents = Storage::disk('local')->get($path) ?? '';
            $missing  = [];

            foreach ((array) $totalsStep->output['totals'] as $metric => $value) {
                if (! $this->documentMentions($contents, (float) $value)) {
                    $missing[] = $metric;
                }
            }

            $checks[] = [
                'criterion' => 'angka_dokumen_cocok',
                'passed'    => $missing === [],
                'detail'    => $missing === []
                    ? 'seluruh total dari ' . $totalsStep->step_key . ' muncul pada ' . basename($path)
                    : 'total tidak ditemukan di dokumen: ' . implode(', ', $missing),
            ];
        }

        return $checks;
    }

    private function documentMentions(string $contents, float $value): bool
    {
        $variants = [
            number_format($value, 2, ',', '.'),
            number_format($value, 0, ',', '.'),
            (string) round($value, 2),
            (string) (int) round($value),
        ];

        foreach (array_unique($variants) as $variant) {
            if (str_contains($contents, $variant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AgentTaskStep>  $steps
     * @return array{criterion: string, passed: bool, detail: string}
     */
    private function evaluateConstraint(string $constraint, mixed $value, $steps, AgentTask $task): array
    {
        $haystack = Str::lower(json_encode($steps->pluck('output')->all(), JSON_UNESCAPED_UNICODE) . ' ' . $task->final_output);

        return match ($constraint) {
            'must_include' => [
                'criterion' => 'memuat_kata_kunci',
                'passed'    => collect((array) $value)->every(fn ($needle) => str_contains($haystack, Str::lower((string) $needle))),
                'detail'    => 'kata kunci wajib: ' . implode(', ', (array) $value),
            ],
            'max_steps' => [
                'criterion' => 'batas_langkah',
                'passed'    => $steps->count() <= (int) $value,
                'detail'    => $steps->count() . ' dari maksimum ' . (int) $value . ' langkah',
            ],
            default => [
                'criterion' => 'batasan_' . $constraint,
                'passed'    => true,
                'detail'    => 'batasan dicatat tanpa pemeriksaan otomatis',
            ],
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AgentTaskStep>  $steps
     * @return array<int, array{path: string, name: string}>
     */
    public function deliverables($steps): array
    {
        return $steps
            ->filter(fn (AgentTaskStep $s) => ! empty($s->output['path']))
            ->map(fn (AgentTaskStep $s) => [
                'path' => (string) $s->output['path'],
                'name' => (string) ($s->output['filename'] ?? basename((string) $s->output['path'])),
                'step' => $s->step_key,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array{criterion: string, passed: bool, detail: string}
     */
    private function evaluate(string $criterion, array $output): array
    {
        [$rule, $argument] = array_pad(explode(':', $criterion, 2), 2, null);

        return match ($rule) {
            'output_not_empty' => [
                'criterion' => $criterion,
                'passed'    => $output !== [],
                'detail'    => $output === [] ? 'keluaran kosong' : 'keluaran terisi',
            ],

            'has' => [
                'criterion' => $criterion,
                'passed'    => isset($output[$argument]) && $output[$argument] !== [] && $output[$argument] !== '' && $output[$argument] !== null,
                'detail'    => isset($output[$argument]) ? "kunci '{$argument}' tersedia" : "kunci '{$argument}' tidak ada pada keluaran",
            ],

            'min_rows' => (function () use ($criterion, $output, $argument) {
                $count = (int) ($output['row_count'] ?? count((array) ($output['rows'] ?? [])));

                return [
                    'criterion' => $criterion,
                    'passed'    => $count >= (int) $argument,
                    'detail'    => "{$count} baris (minimal {$argument})",
                ];
            })(),

            'numeric' => (function () use ($criterion, $output, $argument) {
                $value  = $output[$argument] ?? null;
                $values = is_array($value) ? $value : [$value];
                $passed = $values !== [] && collect($values)->every(fn ($v) => is_numeric($v));

                return [
                    'criterion' => $criterion,
                    'passed'    => $passed,
                    'detail'    => $passed ? "'{$argument}' berisi angka" : "'{$argument}' bukan angka yang sah",
                ];
            })(),

            'contains' => (function () use ($criterion, $output, $argument) {
                $needle   = Str::lower((string) $argument);
                $haystack = Str::lower(json_encode($output, JSON_UNESCAPED_UNICODE) ?: '');
                $found    = str_contains($haystack, $needle);

                if (! $found && ! empty($output['path']) && Storage::disk('local')->exists($output['path'])) {
                    $found = str_contains(Str::lower((string) Storage::disk('local')->get($output['path'])), $needle);
                }

                return [
                    'criterion' => $criterion,
                    'passed'    => $found,
                    'detail'    => $found ? "memuat '{$argument}'" : "tidak memuat '{$argument}'",
                ];
            })(),

            'has_file' => (function () use ($criterion, $output) {
                $path   = (string) ($output['path'] ?? '');
                $exists = $path !== '' && Storage::disk('local')->exists($path) && Storage::disk('local')->size($path) > 0;

                return [
                    'criterion' => $criterion,
                    'passed'    => $exists,
                    'detail'    => $exists ? 'berkas tersimpan: ' . basename($path) : 'berkas hasil tidak ditemukan',
                ];
            })(),

            default => [
                'criterion' => $criterion,
                'passed'    => true,
                'detail'    => 'kriteria tidak dikenali — dilewati tanpa menggugurkan hasil',
            ],
        };
    }
}
