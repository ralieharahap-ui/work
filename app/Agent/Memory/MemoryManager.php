<?php

namespace App\Agent\Memory;

use App\Agent\Contracts\MemoryStore;
use App\Agent\Data\MemoryBundle;
use App\Agent\Data\Reflection;
use App\Models\AgentExperience;
use App\Models\AgentLesson;
use App\Models\AgentProcedure;
use App\Models\AgentTask;
use App\Models\AgentToolStat;
use Illuminate\Support\Str;

/**
 * Pengelola memori jangka panjang: mengambil pengalaman yang relevan sebelum
 * merencanakan, lalu mengubah hasil refleksi menjadi pengalaman, pelajaran,
 * dan prosedur yang dapat dipakai ulang.
 *
 * Prinsipnya: log mentah ≠ memori. Yang disimpan di sini adalah intisari yang
 * sudah diredaksi, bukan salinan seluruh percakapan.
 */
class MemoryManager
{
    public function __construct(
        private readonly MemoryStore $store,
        private readonly Redactor $redactor,
    ) {
    }

    // ── Pengambilan ───────────────────────────────────────────────────────

    public function recall(AgentTask $task): MemoryBundle
    {
        $query   = trim($task->objective . ' ' . $task->task_type . ' ' . Str::limit(json_encode($task->context ?: []) ?: '', 200));
        $filters = ['organization_id' => $task->organization_id, 'task_type' => $task->task_type];
        $limits  = (array) config('agent.memory.retrieval');

        $rank = fn (string $collection, int $limit) => $this->store->rank(
            $this->store->search($collection, $query, $filters),
            $query,
            ['limit' => $limit],
        );

        return new MemoryBundle(
            experiences: $rank('experiences', (int) ($limits['experiences'] ?? 3)),
            lessons: $this->resolveConflicts($rank('lessons', (int) ($limits['lessons'] ?? 5) + 2))
                ->take((int) ($limits['lessons'] ?? 5))->values(),
            procedures: $rank('procedures', (int) ($limits['procedures'] ?? 2)),
            semantic: $this->store->rank(
                $this->store->search('semantic', $query, ['organization_id' => $task->organization_id]),
                $query,
                ['limit' => (int) ($limits['semantic'] ?? 3)],
            ),
            toolStats: $this->toolStats($task->organization_id, $task->task_type),
        );
    }

    /**
     * Ketika dua pelajaran menyinggung hal yang sama tetapi saling
     * bertentangan, yang dipakai adalah yang keyakinannya lebih tinggi; bila
     * setara, yang lebih baru menang. Pelajaran kalah tidak dihapus — hanya
     * tidak ikut dipakai kali ini.
     *
     * @param  \Illuminate\Support\Collection<int, array{item: AgentLesson, score: float, breakdown: array}>  $ranked
     * @return \Illuminate\Support\Collection<int, array{item: AgentLesson, score: float, breakdown: array}>
     */
    public function resolveConflicts($ranked)
    {
        return $ranked
            ->groupBy(fn (array $row) => $row['item']->scope . '|' . ($row['item']->subject ?? '-'))
            ->map(function ($group) {
                if ($group->count() === 1) {
                    return $group->first();
                }

                return $group->sortByDesc(fn (array $row) => [
                    round((float) $row['item']->confidence, 3),
                    optional($row['item']->updated_at)->timestamp ?? 0,
                ])->first();
            })
            ->values()
            ->sortByDesc('score')
            ->values();
    }

    /** @return array<int, array<string, mixed>> */
    public function toolStats(string $organizationId, string $taskType): array
    {
        return AgentToolStat::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->where('task_type', $taskType)->orWhere('task_type', 'general'))
            ->orderByDesc('runs')
            ->get()
            ->map(fn (AgentToolStat $stat) => [
                'tool'         => $stat->tool,
                'task_type'    => $stat->task_type,
                'runs'         => $stat->runs,
                'success_rate' => $stat->successRate(),
                'avg_ms'       => $stat->averageDurationMs(),
                'common_errors'=> $stat->common_errors ?? [],
            ])->all();
    }

    // ── Penyimpanan ───────────────────────────────────────────────────────

    /**
     * Mengubah refleksi menjadi memori: satu pengalaman, sejumlah pelajaran,
     * dan (bila polanya terbukti) satu prosedur yang dapat dipakai ulang.
     *
     * @param  array<string, mixed>  $facts
     */
    public function learn(AgentTask $task, Reflection $reflection, array $facts): AgentExperience
    {
        $experienceId = $this->store->store('experiences', [
            'organization_id'     => $task->organization_id,
            'agent_id'            => $task->agent_id,
            'task_id'             => $task->id,
            'task_type'           => $task->task_type,
            'objective'           => Str::limit($this->redactor->text($task->objective), 500),
            'context'             => $this->redactor->forMemory($task->context ?? []),
            'plan'                => $this->redactor->forMemory($facts['plan'] ?? []),
            'actions'             => $this->redactor->forMemory($facts['actions'] ?? []),
            'result'              => Str::limit($this->redactor->text($reflection->summary), 2000),
            'outcome'             => $reflection->outcome,
            'errors'              => $this->redactor->forMemory($facts['errors'] ?? []),
            'successful_patterns' => $reflection->successfulPatterns,
            'failed_patterns'     => $reflection->failedPatterns,
            'lessons'             => array_map(static fn (array $l) => $l['lesson'], $reflection->lessons),
            'reusable_strategy'   => $reflection->reusableStrategy,
            'tools'               => $facts['tools'] ?? [],
            'confidence'          => $reflection->confidence,
            'duration_seconds'    => (int) ($facts['duration_seconds'] ?? 0),
        ]);

        /** @var AgentExperience $experience */
        $experience = $this->store->retrieve('experiences', $experienceId);

        foreach ($reflection->lessons as $lesson) {
            $this->rememberLesson($task, $experience, $lesson);
        }

        if ($reflection->reliable && $reflection->outcome !== 'FAILURE') {
            $this->rememberProcedure($task, $facts, $reflection);
        }

        return $experience;
    }

    /**
     * Menyimpan pelajaran baru, atau menguatkan pelajaran lama yang identik.
     *
     * @param  array{scope: string, subject: ?string, trigger: string, lesson: string, recommendation: string, payload: array<string, mixed>, confidence: float}  $lesson
     */
    public function rememberLesson(AgentTask $task, ?AgentExperience $experience, array $lesson): AgentLesson
    {
        $existing = AgentLesson::where('organization_id', $task->organization_id)
            ->where('task_type', $task->task_type)
            ->where('scope', $lesson['scope'])
            ->where('subject', $lesson['subject'])
            ->where('trigger', $lesson['trigger'])
            ->first();

        $step = (float) config('agent.memory.confidence_step', 0.08);

        if ($existing) {
            $existing->fill([
                'lesson'         => $lesson['lesson'],
                'recommendation' => $lesson['recommendation'],
                'payload'        => $this->redactor->forMemory($lesson['payload']),
                'confidence'     => min(0.98, $existing->confidence + $step),
                'success_count'  => $existing->success_count + 1,
                'is_active'      => true,
            ])->save();

            return $existing;
        }

        $id = $this->store->store('lessons', [
            'organization_id' => $task->organization_id,
            'experience_id'   => $experience?->id,
            'task_type'       => $task->task_type,
            'scope'           => $lesson['scope'],
            'subject'         => $lesson['subject'],
            'trigger'         => $lesson['trigger'],
            'lesson'          => $this->redactor->text($lesson['lesson']),
            'recommendation'  => $this->redactor->text($lesson['recommendation']),
            'payload'         => $this->redactor->forMemory($lesson['payload']),
            'evidence'        => $lesson['evidence'] ?? [],
            'confidence'      => $lesson['confidence'],
        ]);

        /** @var AgentLesson $model */
        $model = $this->store->retrieve('lessons', $id);

        return $model;
    }

    /**
     * Menyusun/memperbarui prosedur dari rangkaian langkah yang berhasil.
     * Rangkaian tool yang berbeda menghasilkan versi baru, sehingga statistik
     * keberhasilan versi lama tetap utuh.
     *
     * @param  array<string, mixed>  $facts
     */
    public function rememberProcedure(AgentTask $task, array $facts, Reflection $reflection): ?AgentProcedure
    {
        $steps = array_values(array_filter(
            (array) ($facts['successful_steps'] ?? []),
            static fn (array $step) => ! empty($step['tool']),
        ));

        if (count($steps) < 2) {
            return null; // Terlalu sederhana untuk dijadikan prosedur.
        }

        $name      = Str::upper(Str::snake($task->task_type));
        $signature = implode('>', array_column($steps, 'tool'));

        $skeleton = array_map(static fn (array $step) => [
            'objective'        => $step['objective'],
            'tool'             => $step['tool'],
            'success_criteria' => $step['success_criteria'] ?? [],
            'risk_level'       => $step['risk_level'] ?? 'low',
            'input_keys'       => array_values(array_keys($step['inputs'] ?? [])),
        ], $steps);

        $existing = AgentProcedure::where('organization_id', $task->organization_id)
            ->where('name', $name)
            ->orderByDesc('version')
            ->get()
            ->first(fn (AgentProcedure $p) => implode('>', array_column($p->steps, 'tool')) === $signature);

        $success = $reflection->outcome === 'SUCCESS';

        if ($existing) {
            // Prosedur yang memang dipakai untuk merencanakan task ini sudah
            // dihitung pada tahap reinforce(); di sini cukup menyegarkan
            // kerangka langkahnya agar tidak terhitung dua kali.
            $alreadyCounted = ($task->plan['procedure_id'] ?? null) === $existing->id;

            $existing->fill($alreadyCounted ? [
                'steps' => $skeleton,
            ] : [
                'use_count'     => $existing->use_count + 1,
                'success_count' => $existing->success_count + ($success ? 1 : 0),
                'failure_count' => $existing->failure_count + ($success ? 0 : 1),
                'last_used_at'  => now(),
                'steps'         => $skeleton,
            ]);

            $existing->success_rate = $existing->recomputeSuccessRate();
            $existing->save();

            return $existing;
        }

        $version = (int) AgentProcedure::where('organization_id', $task->organization_id)
            ->where('name', $name)->max('version') + 1;

        $id = $this->store->store('procedures', [
            'organization_id' => $task->organization_id,
            'name'            => $name,
            'task_type'       => $task->task_type,
            'version'         => $version,
            'trigger'         => 'Pekerjaan bertipe ' . $task->task_type . ' — ' . Str::limit($this->redactor->text($task->objective), 120),
            'preconditions'   => $facts['preconditions'] ?? [],
            'steps'           => $skeleton,
            'success_rate'    => $success ? 0.67 : 0.33,
            'use_count'       => 1,
            'success_count'   => $success ? 1 : 0,
            'failure_count'   => $success ? 0 : 1,
            'last_used_at'    => now(),
        ]);

        /** @var AgentProcedure $procedure */
        $procedure = $this->store->retrieve('procedures', $id);

        return $procedure;
    }

    /**
     * Memperbarui nilai memori yang benar-benar dipakai pada sebuah task:
     * yang membantu naik keyakinannya, yang menyesatkan turun.
     */
    public function reinforce(AgentTask $task, string $outcome): void
    {
        $step    = (float) config('agent.memory.confidence_step', 0.08);
        $success = $outcome === 'SUCCESS';
        $delta   = $success ? $step : -$step;

        foreach (AgentExperience::whereIn('id', (array) ($task->experience_ids ?: []))->get() as $experience) {
            $experience->fill([
                'use_count'     => $experience->use_count + 1,
                'success_count' => $experience->success_count + ($success ? 1 : 0),
                'failure_count' => $experience->failure_count + ($success ? 0 : 1),
                'confidence'    => max(0.05, min(0.98, $experience->confidence + $delta)),
                'last_used_at'  => now(),
            ]);
            $experience->is_obsolete = $experience->confidence < (float) config('agent.memory.obsolete_below', 0.15);
            $experience->save();
        }

        foreach (AgentLesson::whereIn('id', (array) ($task->lesson_ids ?: []))->get() as $lesson) {
            $lesson->fill([
                'use_count'     => $lesson->use_count + 1,
                'success_count' => $lesson->success_count + ($success ? 1 : 0),
                'failure_count' => $lesson->failure_count + ($success ? 0 : 1),
                'confidence'    => max(0.05, min(0.98, $lesson->confidence + $delta)),
                'last_used_at'  => now(),
            ]);
            $lesson->is_active = $lesson->confidence >= (float) config('agent.memory.obsolete_below', 0.15);
            $lesson->save();
        }

        foreach (AgentProcedure::whereIn('id', (array) (($task->plan['procedure_id'] ?? null) ? [$task->plan['procedure_id']] : []))->get() as $procedure) {
            $procedure->fill([
                'use_count'     => $procedure->use_count + 1,
                'success_count' => $procedure->success_count + ($success ? 1 : 0),
                'failure_count' => $procedure->failure_count + ($success ? 0 : 1),
                'last_used_at'  => now(),
            ]);
            $procedure->success_rate = $procedure->recomputeSuccessRate();
            $procedure->save();
        }
    }

    /** Pengetahuan umum (memori semantik) yang tidak terikat satu task. */
    public function rememberFact(string $organizationId, string $subject, string $content, string $source = 'agent'): void
    {
        $existing = \App\Models\AgentMemory::where('organization_id', $organizationId)
            ->where('subject', $subject)->first();

        if ($existing) {
            $this->store->update('semantic', $existing->id, [
                'content'    => $this->redactor->text($content),
                'confidence' => min(0.98, $existing->confidence + 0.05),
                'use_count'  => $existing->use_count + 1,
                'embedding'  => null,
            ]);

            return;
        }

        $this->store->store('semantic', [
            'organization_id' => $organizationId,
            'kind'            => 'semantic',
            'subject'         => $subject,
            'content'         => $this->redactor->text($content),
            'source'          => $source,
        ]);
    }

    /** Statistik performa tool diperbarui setiap kali tool dipanggil. */
    public function recordToolOutcome(
        string $organizationId,
        string $tool,
        string $taskType,
        bool $success,
        int $durationMs,
        ?string $errorClass = null,
    ): void {
        $stat = AgentToolStat::firstOrCreate(
            ['organization_id' => $organizationId, 'tool' => $tool, 'task_type' => $taskType],
            ['runs' => 0, 'successes' => 0, 'failures' => 0, 'total_duration_ms' => 0],
        );

        $errors = $stat->common_errors ?? [];

        if (! $success && $errorClass) {
            $errors[$errorClass] = (int) ($errors[$errorClass] ?? 0) + 1;
            arsort($errors);
            $errors = array_slice($errors, 0, 5, true);
        }

        $stat->fill([
            'runs'              => $stat->runs + 1,
            'successes'         => $stat->successes + ($success ? 1 : 0),
            'failures'          => $stat->failures + ($success ? 0 : 1),
            'total_duration_ms' => $stat->total_duration_ms + max(0, $durationMs),
            'common_errors'     => $errors,
            'last_used_at'      => now(),
        ])->save();
    }
}
