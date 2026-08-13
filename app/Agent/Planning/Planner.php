<?php

namespace App\Agent\Planning;

use App\Agent\Data\LlmRequest;
use App\Agent\Data\MemoryBundle;
use App\Agent\Data\Plan;
use App\Agent\Data\PlanStep;
use App\Agent\Events\EventRecorder;
use App\Agent\Events\EventType;
use App\Agent\Llm\LlmManager;
use App\Agent\Llm\Providers\ScriptedProvider;
use App\Agent\Tools\ToolRegistry;
use App\Models\AgentLesson;
use App\Models\AgentProcedure;
use App\Models\AgentTask;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengubah tujuan menjadi rencana terstruktur.
 *
 * Urutan pertimbangannya: prosedur yang sudah terbukti → pengalaman serupa →
 * penyusunan baru. Pelajaran dari kegagalan lampau disuntikkan ke input
 * langkah sebelum dijalankan, sehingga agent tidak mengulang kesalahan yang
 * sama.
 */
class Planner
{
    /** Ambang kelayakan sebuah prosedur untuk dipakai ulang apa adanya. */
    private const PROCEDURE_MIN_SCORE   = 0.2;
    private const PROCEDURE_MIN_SUCCESS = 0.6;

    public function __construct(
        private readonly LlmManager $llm,
        private readonly ToolRegistry $registry,
        private readonly EventRecorder $events,
    ) {
    }

    /**
     * Tahap PERCEIVE → UNDERSTAND: menetapkan jenis pekerjaan, hasil yang
     * diharapkan, dan tingkat risikonya sebelum satu langkah pun disusun.
     *
     * @return array<string, mixed>
     */
    public function understand(AgentTask $task): array
    {
        $provider = $this->llm->provider();

        $request = new LlmRequest(
            system: $this->understandingSystemPrompt(),
            messages: [['role' => 'user', 'content' => $task->objective]],
            purpose: 'classify',
            metadata: ['context' => $task->context ?? []],
        );

        try {
            $understanding = $provider->structured($request, $this->understandingSchema());
        } catch (Throwable $e) {
            report($e);
            $understanding = (new ScriptedProvider())->structured($request, $this->understandingSchema());
        }

        $taskType = (string) ($understanding['task_type'] ?? 'general');

        $task->fill([
            'task_type'       => $taskType,
            'title'           => $task->title ?: Str::limit((string) ($understanding['title'] ?? $task->objective), 70, ''),
            'risk_level'      => in_array($understanding['risk_level'] ?? 'low', ['low', 'medium', 'high'], true)
                ? (string) $understanding['risk_level'] : 'low',
            'expected_output' => $task->expected_output ?: (string) ($understanding['expected_output'] ?? ''),
        ])->save();

        $this->events->record($task, EventType::TASK_UNDERSTOOD,
            "Pekerjaan dipahami sebagai '{$taskType}' dengan risiko {$task->risk_level}.",
            ['understanding' => $understanding]);

        return $understanding;
    }

    /**
     * Tahap PLAN. `$issues` berisi temuan pemeriksaan yang gagal ketika
     * rencana disusun ulang.
     *
     * @param  array<int, string>  $issues
     */
    public function createPlan(AgentTask $task, MemoryBundle $memories, array $issues = []): Plan
    {
        $draft     = $this->draft($task, $memories, $issues);
        $procedure = $this->usableProcedure($memories);

        if ($procedure) {
            $draft = $this->alignToProcedure($draft, $procedure, $task);
        }

        [$plan, $appliedLessons] = $this->applyLessons($draft, $memories->lessonModels(), $task);

        $plan = new Plan(
            taskType: $plan->taskType,
            steps: $plan->steps,
            strategy: $plan->strategy,
            origin: $plan->origin,
            procedureId: $plan->procedureId,
            confidence: $this->planningConfidence($task, $memories, $plan, $appliedLessons),
            notes: $plan->notes,
        );

        if ($appliedLessons !== []) {
            $task->lesson_ids = array_values(array_unique(array_merge(
                (array) ($task->lesson_ids ?: []),
                array_column($appliedLessons, 'id'),
            )));
            $task->save();

            $this->events->record($task, EventType::LESSON_APPLIED,
                count($appliedLessons) . ' pelajaran dari pekerjaan sebelumnya diterapkan pada rencana.',
                ['lessons' => $appliedLessons]);
        }

        return $plan;
    }

    // ── Penyusunan rencana dasar ──────────────────────────────────────────

    /** @param array<int, string> $issues */
    private function draft(AgentTask $task, MemoryBundle $memories, array $issues): Plan
    {
        $provider = $this->llm->provider();
        $tools    = $this->registry->namesFor($task->agent);

        $request = new LlmRequest(
            system: $this->planningSystemPrompt($task),
            messages: [['role' => 'user', 'content' => $this->planningUserPrompt($task, $memories, $issues)]],
            purpose: 'plan',
            metadata: [
                'task_type'       => $task->task_type,
                'context'         => $task->context ?? [],
                'available_tools' => $tools,
                'issues'          => $issues,
            ],
        );

        try {
            $raw = $provider->structured($request, $this->planSchema());
            $origin = $provider->name();
        } catch (Throwable $e) {
            report($e);
            // Penyedia utama gagal — jatuh ke perencana heuristik agar
            // pekerjaan tetap berjalan alih-alih berhenti total.
            $raw = (new ScriptedProvider())->structured($request, $this->planSchema());
            $origin = 'scripted';
        }

        $steps = [];
        foreach ((array) ($raw['steps'] ?? []) as $index => $rawStep) {
            $step = PlanStep::fromArray((array) $rawStep, $index);

            if ($step->tool !== null && ! in_array($step->tool, $tools, true)) {
                // Tool khayalan tidak pernah dijalankan; langkahnya diturunkan
                // menjadi catatan agar rencana tetap utuh dan terlihat.
                $step = new PlanStep(
                    id: $step->id,
                    objective: $step->objective,
                    tool: 'agent.note',
                    inputs: ['topic' => $step->objective],
                    dependencies: $step->dependencies,
                    successCriteria: ['output_not_empty'],
                );
            }

            $steps[] = $step;
        }

        if ($steps === []) {
            $steps[] = new PlanStep('step_1', 'Rangkum permintaan menjadi langkah kerja', 'agent.note',
                ['topic' => Str::limit($task->objective, 80)], [], ['output_not_empty']);
        }

        return new Plan(
            taskType: $task->task_type,
            steps: $steps,
            strategy: (string) ($raw['strategy'] ?? ''),
            origin: $origin,
            notes: $issues === [] ? [] : ['Rencana disusun ulang setelah: ' . implode('; ', $issues)],
        );
    }

    private function usableProcedure(MemoryBundle $memories): ?AgentProcedure
    {
        $row = $memories->procedures->first();

        if (! $row) {
            return null;
        }

        /** @var AgentProcedure $procedure */
        $procedure = $row['item'];

        return $row['score'] >= self::PROCEDURE_MIN_SCORE && $procedure->success_rate >= self::PROCEDURE_MIN_SUCCESS
            ? $procedure
            : null;
    }

    /**
     * Menyelaraskan rencana dengan prosedur yang sudah terbukti: urutan tool
     * mengikuti prosedur, sedangkan nilai input diambil dari rencana baru
     * (karena input bergantung konteks pekerjaan hari ini).
     */
    private function alignToProcedure(Plan $draft, AgentProcedure $procedure, AgentTask $task): Plan
    {
        $draftSignature     = implode('>', array_filter(array_map(fn (PlanStep $s) => $s->tool, $draft->steps)));
        $procedureSignature = implode('>', array_column($procedure->steps, 'tool'));

        if ($draftSignature === $procedureSignature) {
            return new Plan(
                taskType: $draft->taskType,
                steps: $draft->steps,
                strategy: $draft->strategy,
                origin: 'procedure',
                procedureId: $procedure->id,
                notes: array_merge($draft->notes, [
                    'Mengikuti prosedur ' . $procedure->label() . ' (keberhasilan '
                        . round($procedure->success_rate * 100) . '%, dipakai ' . $procedure->use_count . '×).',
                ]),
            );
        }

        $unused = $draft->steps;
        $steps  = [];

        foreach ($procedure->steps as $index => $blueprint) {
            $matchIndex = null;

            foreach ($unused as $i => $candidate) {
                if ($candidate->tool === ($blueprint['tool'] ?? null)) {
                    $matchIndex = $i;
                    break;
                }
            }

            if ($matchIndex !== null) {
                $match = $unused[$matchIndex];
                unset($unused[$matchIndex]);

                $steps[] = new PlanStep(
                    id: 'step_' . ($index + 1),
                    objective: $match->objective,
                    tool: $match->tool,
                    inputs: $match->inputs,
                    dependencies: $index === 0 ? [] : ['step_' . $index],
                    successCriteria: $match->successCriteria ?: (array) ($blueprint['success_criteria'] ?? []),
                    riskLevel: $match->riskLevel,
                );

                continue;
            }

            // Langkah prosedur yang tak punya padanan: bangun dari kerangka,
            // input diambil dari konteks pekerjaan bila namanya cocok.
            $inputs = [];
            foreach ((array) ($blueprint['input_keys'] ?? []) as $key) {
                if (isset($task->context[$key])) {
                    $inputs[$key] = $task->context[$key];
                }
            }

            if ($index > 0 && $inputs === []) {
                $inputs['source_step'] = 'step_' . $index;
            }

            $steps[] = new PlanStep(
                id: 'step_' . ($index + 1),
                objective: (string) ($blueprint['objective'] ?? 'Langkah prosedur'),
                tool: $blueprint['tool'] ?? null,
                inputs: $inputs,
                dependencies: $index === 0 ? [] : ['step_' . $index],
                successCriteria: (array) ($blueprint['success_criteria'] ?? []),
                riskLevel: (string) ($blueprint['risk_level'] ?? 'low'),
            );
        }

        return new Plan(
            taskType: $draft->taskType,
            steps: $steps,
            strategy: $draft->strategy,
            origin: 'procedure',
            procedureId: $procedure->id,
            notes: array_merge($draft->notes, [
                'Urutan langkah mengikuti prosedur ' . $procedure->label()
                    . ' yang tingkat keberhasilannya ' . round($procedure->success_rate * 100) . '%.',
            ]),
        );
    }

    /**
     * Menyuntikkan pelajaran ke dalam rencana — inilah tempat pengetahuan
     * lama berubah menjadi tindakan nyata pada pekerjaan baru.
     *
     * @param  array<int, AgentLesson>  $lessons
     * @return array{0: Plan, 1: array<int, array<string, mixed>>}
     */
    public function applyLessons(Plan $plan, array $lessons, AgentTask $task): array
    {
        $applied = [];
        $notes   = $plan->notes;
        $steps   = $plan->steps;

        foreach ($lessons as $lesson) {
            $payload = (array) ($lesson->payload ?? []);
            $type    = (string) ($payload['type'] ?? '');
            $used    = false;

            foreach ($steps as $step) {
                $used = match ($type) {
                    'column_map'    => $this->applyColumnMap($step, $payload) || $used,
                    'dataset_alias' => $this->applyDatasetAlias($step, $payload) || $used,
                    'input_default' => $this->applyInputDefaults($step, $payload) || $used,
                    default         => $used,
                };
            }

            if ($used) {
                $applied[] = [
                    'id'         => $lesson->id,
                    'lesson'     => $lesson->lesson,
                    'confidence' => round((float) $lesson->confidence, 3),
                ];
                $notes[] = 'Pelajaran diterapkan: ' . $lesson->lesson;
            }
        }

        return [
            new Plan(
                taskType: $plan->taskType,
                steps: $steps,
                strategy: $plan->strategy,
                origin: $plan->origin,
                procedureId: $plan->procedureId,
                confidence: $plan->confidence,
                notes: $notes,
            ),
            $applied,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function applyColumnMap(PlanStep $step, array $payload): bool
    {
        if ($step->tool !== 'spreadsheet.read' || empty($payload['column_map'])) {
            return false;
        }

        $target  = (string) ($step->inputs['dataset'] ?? '');
        $pattern = (string) ($payload['dataset_pattern'] ?? $payload['dataset'] ?? '');

        // Pelajaran berlaku bila nama berkas sama persis atau cocok dengan pola
        // keluarga berkas (mis. penjualan-*-*.csv untuk laporan tiap bulan).
        if ($pattern !== '' && $target !== $pattern && ! fnmatch($pattern, $target)) {
            return false;
        }

        $step->inputs['column_map'] = array_merge(
            (array) ($step->inputs['column_map'] ?? []),
            (array) $payload['column_map'],
        );

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function applyDatasetAlias(PlanStep $step, array $payload): bool
    {
        if ($step->tool !== 'spreadsheet.read' || empty($payload['dataset'])) {
            return false;
        }

        if (($step->inputs['dataset'] ?? null) !== ($payload['alias'] ?? null)) {
            return false;
        }

        $step->inputs['dataset'] = (string) $payload['dataset'];

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function applyInputDefaults(PlanStep $step, array $payload): bool
    {
        if (($payload['tool'] ?? null) !== $step->tool || empty($payload['inputs'])) {
            return false;
        }

        $step->inputs = array_merge((array) $payload['inputs'], $step->inputs);

        return true;
    }

    /**
     * Keyakinan perencanaan dihitung dari bukti: prosedur yang terbukti,
     * pengalaman serupa yang berhasil, dan pelajaran yang relevan menaikkannya;
     * penyusunan ulang menurunkannya.
     *
     * @param  array<int, array<string, mixed>>  $appliedLessons
     */
    private function planningConfidence(AgentTask $task, MemoryBundle $memories, Plan $plan, array $appliedLessons): float
    {
        $confidence = 0.5;

        if ($plan->origin === 'procedure' && $procedure = $memories->bestProcedure()) {
            $confidence += 0.2 * $procedure->success_rate;
        }

        if ($row = $memories->experiences->first()) {
            /** @var \App\Models\AgentExperience $experience */
            $experience = $row['item'];
            $confidence += 0.2 * $row['score'] * ($experience->outcome === 'SUCCESS' ? 1 : 0.4);
        }

        $confidence += min(0.1, 0.04 * count($appliedLessons));
        $confidence -= 0.12 * $task->replans;

        // Rencana panjang lebih rawan meleset daripada rencana ringkas.
        $confidence -= max(0, count($plan->steps) - 6) * 0.02;

        return round(max(0.05, min(0.95, $confidence)), 4);
    }

    // ── Prompt ────────────────────────────────────────────────────────────

    private function understandingSystemPrompt(): string
    {
        return 'Anda adalah asisten kantor yang memilah instruksi pekerjaan berbahasa Indonesia. '
            . 'Tentukan jenis pekerjaan (report_generation, data_comparison, email_handling, calendar_scheduling, '
            . 'document_preparation, followup, research, atau general), judul singkat, tingkat risiko (low/medium/high; '
            . 'tindakan yang mengirim, menghapus, atau mengubah data pihak lain berisiko tinggi), serta bentuk hasil '
            . 'yang diharapkan. Jangan mengarang kebutuhan yang tidak diminta.';
    }

    private function planningSystemPrompt(AgentTask $task): string
    {
        $tools = collect($this->registry->definitions($task->agent))
            ->map(fn (array $definition) => sprintf(
                '- %s (risiko %s): %s Input: %s',
                $definition['name'],
                $definition['risk_level'],
                $definition['description'],
                implode(', ', array_keys($definition['input_schema'])) ?: 'tanpa input',
            ))
            ->implode("\n");

        return "Anda adalah perencana kerja seorang asisten kantor digital.\n"
            . "Susun rencana langkah demi langkah memakai HANYA tool berikut:\n{$tools}\n\n"
            . "Aturan:\n"
            . "- Setiap langkah memakai tepat satu tool dan punya kriteria keberhasilan yang dapat diperiksa.\n"
            . "- Rujuk keluaran langkah sebelumnya lewat inputs.source_step (atau left_step/right_step).\n"
            . "- Tandai langkah yang mengirim/mengubah sesuatu di luar aplikasi sebagai risk_level \"high\".\n"
            . "- Jangan menambah langkah yang tidak diminta; ringkas lebih baik daripada panjang.";
    }

    /** @param array<int, string> $issues */
    private function planningUserPrompt(AgentTask $task, MemoryBundle $memories, array $issues): string
    {
        $lines = [
            'Tujuan: ' . $task->objective,
            'Jenis pekerjaan: ' . $task->task_type,
            'Hasil yang diharapkan: ' . ($task->expected_output ?: 'tidak dinyatakan'),
            'Konteks: ' . (json_encode($task->context ?: [], JSON_UNESCAPED_UNICODE) ?: '{}'),
        ];

        if ($task->constraints) {
            $lines[] = 'Batasan: ' . (json_encode($task->constraints, JSON_UNESCAPED_UNICODE) ?: '{}');
        }

        foreach ($memories->experiences as $row) {
            $lines[] = sprintf(
                'Pengalaman serupa (%s, skor %.2f): %s — strategi: %s',
                $row['item']->outcome, $row['score'], $row['item']->objective, $row['item']->reusable_strategy ?: '-',
            );
        }

        foreach ($memories->lessons as $row) {
            $lines[] = 'Pelajaran: ' . $row['item']->lesson . ' → ' . $row['item']->recommendation;
        }

        foreach ($memories->toolStats as $stat) {
            if ($stat['runs'] >= 3) {
                $lines[] = sprintf('Statistik tool %s: keberhasilan %.0f%% dari %d pemakaian.',
                    $stat['tool'], $stat['success_rate'] * 100, $stat['runs']);
            }
        }

        if ($issues !== []) {
            $lines[] = 'Rencana sebelumnya gagal pada: ' . implode('; ', $issues) . '. Susun pendekatan yang memperbaiki hal tersebut.';
        }

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    private function understandingSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_type'       => ['type' => 'string'],
                'title'           => ['type' => 'string'],
                'risk_level'      => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'expected_output' => ['type' => 'string'],
                'keywords'        => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['task_type', 'title', 'risk_level'],
        ];
    }

    /** @return array<string, mixed> */
    private function planSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'strategy' => ['type' => 'string'],
                'steps' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id'               => ['type' => 'string'],
                            'objective'        => ['type' => 'string'],
                            'tool'             => ['type' => 'string'],
                            'inputs'           => ['type' => 'object'],
                            'dependencies'     => ['type' => 'array', 'items' => ['type' => 'string']],
                            'success_criteria' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'risk_level'       => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        ],
                        'required' => ['id', 'objective', 'tool'],
                    ],
                ],
            ],
            'required' => ['steps'],
        ];
    }
}
