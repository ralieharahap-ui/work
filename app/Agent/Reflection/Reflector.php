<?php

namespace App\Agent\Reflection;

use App\Agent\Data\LlmRequest;
use App\Agent\Data\Reflection;
use App\Agent\Data\Verification;
use App\Agent\Llm\LlmManager;
use App\Agent\Llm\Providers\ScriptedProvider;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menjawab delapan pertanyaan refleksi setelah pekerjaan berakhir: apa
 * tujuannya, strategi apa yang dipakai, apa yang berhasil, apa yang gagal,
 * mengapa gagal, bagaimana memperbaikinya, pola apa yang dapat dipakai ulang,
 * dan seberapa layak pengalaman ini dipercaya.
 *
 * Fakta dikumpulkan secara deterministik dari jejak eksekusi; model bahasa
 * hanya dipakai untuk merangkumnya menjadi kalimat — sehingga pelajaran yang
 * tersimpan selalu berpijak pada bukti, bukan pada karangan.
 */
class Reflector
{
    public function __construct(private readonly LlmManager $llm)
    {
    }

    public function reflect(AgentTask $task, Verification $verification): Reflection
    {
        $facts   = $this->facts($task, $verification);
        $outcome = $this->outcome($task, $verification, $facts);

        $narrative = $this->narrate($task, $facts + ['outcome' => $outcome]);

        $lessons = array_merge(
            $this->pendingLessons($task),
            $this->derivedLessons($task, $verification, $facts),
        );

        $reliable = $outcome === 'SUCCESS'
            && $verification->score >= 0.8
            && $task->replans <= 1;

        return new Reflection(
            outcome: $outcome,
            summary: (string) ($narrative['summary'] ?? ''),
            successfulPatterns: $facts['successful_patterns'],
            failedPatterns: $facts['failed_patterns'],
            lessons: $lessons,
            reusableStrategy: (string) ($narrative['reusable_strategy'] ?: ($task->plan['strategy'] ?? '')),
            confidence: $this->confidence($task, $verification, $facts),
            reliable: $reliable,
        );
    }

    /**
     * Fakta mentah hasil eksekusi — dasar seluruh penilaian di bawahnya.
     *
     * @return array<string, mixed>
     */
    public function facts(AgentTask $task, Verification $verification): array
    {
        $steps     = $task->steps()->get();
        $succeeded = $steps->where('status', 'succeeded');
        $failed    = $steps->where('status', 'failed');

        $errors = $steps
            ->filter(fn (AgentTaskStep $s) => $s->error !== null)
            ->map(fn (AgentTaskStep $s) => [
                'step'  => $s->step_key,
                'tool'  => $s->tool,
                'class' => $s->error_class,
                'error' => Str::limit((string) $s->error, 240),
            ])->values()->all();

        $recoveries = array_values((array) ($task->working_memory['recoveries'] ?? []));

        $successfulPatterns = [];
        if ($succeeded->isNotEmpty()) {
            $successfulPatterns[] = 'Rangkaian tool ' . $succeeded->pluck('tool')->filter()->implode(' → ') . ' menyelesaikan pekerjaan.';
        }
        foreach ($recoveries as $recovery) {
            $successfulPatterns[] = 'Pemulihan berhasil: ' . $recovery;
        }
        if ($verification->passed) {
            $successfulPatterns[] = 'Seluruh pemeriksaan hasil terpenuhi (' . count($verification->checks) . ' kriteria).';
        }

        $failedPatterns = [];
        foreach ($errors as $error) {
            $failedPatterns[] = sprintf('%s gagal (%s) pada %s.', $error['tool'] ?? 'langkah', $error['class'] ?? 'unknown', $error['step']);
        }
        foreach ($verification->issues as $issue) {
            $failedPatterns[] = 'Pemeriksaan gagal: ' . $issue;
        }

        return [
            'plan'             => $task->plan ?? [],
            'strategy'         => (string) ($task->plan['strategy'] ?? ''),
            'objective'        => $task->objective,
            'tools'            => $succeeded->pluck('tool')->filter()->values()->all(),
            'actions'          => $steps->map(fn (AgentTaskStep $s) => [
                'step'      => $s->step_key,
                'tool'      => $s->tool,
                'status'    => $s->status,
                'attempts'  => $s->attempts,
                'observation' => Str::limit((string) $s->observation, 240),
            ])->values()->all(),
            'errors'             => $errors,
            'recoveries'         => $recoveries,
            'failed_steps'       => $failed->pluck('step_key')->values()->all(),
            'successful_steps'   => $succeeded->map(fn (AgentTaskStep $s) => [
                'objective'        => $s->objective,
                'tool'             => $s->tool,
                'inputs'           => $s->inputs ?? [],
                'success_criteria' => $s->success_criteria ?? [],
                'risk_level'       => $s->risk_level,
            ])->values()->all(),
            'successful_patterns' => $successfulPatterns,
            'failed_patterns'     => $failedPatterns,
            'duration_seconds'    => $task->started_at ? max(0, (int) $task->started_at->diffInSeconds(now())) : 0,
            'step_success_ratio'  => $steps->count() > 0 ? round($succeeded->count() / $steps->count(), 4) : 0.0,
        ];
    }

    /** @param array<string, mixed> $facts */
    private function outcome(AgentTask $task, Verification $verification, array $facts): string
    {
        if ($task->status === AgentTask::CANCELLED) {
            return 'FAILURE';
        }

        if ($verification->passed && $facts['step_success_ratio'] >= 0.99) {
            return 'SUCCESS';
        }

        if ($verification->score >= 0.5 && $facts['step_success_ratio'] >= 0.5) {
            return 'PARTIAL';
        }

        return 'FAILURE';
    }

    /**
     * Pelajaran yang sudah terbukti selama eksekusi (pemulihan yang berhasil)
     * dicatat runtime dan diambil di sini.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingLessons(AgentTask $task): array
    {
        return array_values(array_map(
            static fn (array $lesson) => $lesson + ['evidence' => $lesson['evidence'] ?? []],
            (array) ($task->working_memory['pending_lessons'] ?? []),
        ));
    }

    /**
     * Pelajaran tambahan yang disimpulkan dari pola kegagalan yang tersisa.
     *
     * @param  array<string, mixed>  $facts
     * @return array<int, array<string, mixed>>
     */
    private function derivedLessons(AgentTask $task, Verification $verification, array $facts): array
    {
        $lessons = [];

        foreach ($facts['errors'] as $error) {
            if (in_array($error['class'], ['missing_integration', 'disabled'], true)) {
                $lessons[] = [
                    'scope'          => 'policy',
                    'subject'        => $error['tool'],
                    'trigger'        => 'Sebelum memakai ' . $error['tool'],
                    'lesson'         => 'Tool ' . $error['tool'] . ' membutuhkan akses yang belum diberikan.',
                    'recommendation' => 'Periksa ketersediaan akses lebih dulu, atau minta akses kepada pemilik pekerjaan sebelum menyusun langkah ini.',
                    'payload'        => ['type' => 'precondition', 'tool' => $error['tool'], 'requires_access' => true],
                    'evidence'       => [$error['error']],
                    'confidence'     => 0.6,
                ];
            }
        }

        foreach ($verification->issues as $issue) {
            if (str_starts_with($issue, 'angka_dokumen_cocok')) {
                $lessons[] = [
                    'scope'          => 'process',
                    'subject'        => 'document.create',
                    'trigger'        => 'Menyusun dokumen dari hasil perhitungan',
                    'lesson'         => 'Angka hasil perhitungan belum tentu ikut tersalin ke dokumen.',
                    'recommendation' => 'Cantumkan seluruh total pada dokumen dan periksa ulang sebelum pekerjaan ditutup.',
                    'payload'        => ['type' => 'checklist', 'tool' => 'document.create'],
                    'evidence'       => [$issue],
                    'confidence'     => 0.55,
                ];
            }
        }

        return $lessons;
    }

    /**
     * Keyakinan menyeluruh: gabungan bukti pemeriksaan, kelancaran eksekusi,
     * dan biaya penyusunan ulang. Bukan angka yang dikarang model.
     *
     * @param  array<string, mixed>  $facts
     */
    private function confidence(AgentTask $task, Verification $verification, array $facts): float
    {
        $planning = (float) ($task->confidence['planning'] ?? 0.5);

        $value = 0.5 * $verification->score
            + 0.3 * (float) $facts['step_success_ratio']
            + 0.2 * $planning;

        $value -= 0.05 * count($facts['errors']);
        $value -= 0.1 * $task->replans;

        return round(max(0.05, min(0.98, $value)), 4);
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    private function narrate(AgentTask $task, array $facts): array
    {
        $request = new LlmRequest(
            system: 'Anda merangkum hasil kerja asisten kantor menjadi catatan pengalaman singkat berbahasa Indonesia. '
                . 'Rangkum apa yang dikerjakan, apa yang berhasil, apa yang menghambat, dan strategi yang layak dipakai ulang. '
                . 'Jangan mengarang fakta di luar data yang diberikan, dan jangan menuliskan data rahasia.',
            messages: [['role' => 'user', 'content' => json_encode($facts, JSON_UNESCAPED_UNICODE) ?: '{}']],
            purpose: 'reflect',
            metadata: $facts,
        );

        $schema = [
            'type' => 'object',
            'properties' => [
                'summary'           => ['type' => 'string'],
                'reusable_strategy' => ['type' => 'string'],
            ],
            'required' => ['summary'],
        ];

        try {
            return $this->llm->provider()->structured($request, $schema);
        } catch (Throwable $e) {
            report($e);

            return (new ScriptedProvider())->structured($request, $schema);
        }
    }
}
