<?php

namespace App\Agent\Runtime;

use App\Agent\Data\Plan;
use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolResult;
use App\Agent\Data\Verification;
use App\Agent\Events\EventRecorder;
use App\Agent\Events\EventType;
use App\Agent\Execution\ErrorClassifier;
use App\Agent\Execution\Executor;
use App\Agent\Execution\Recovery;
use App\Agent\Execution\RecoveryPlanner;
use App\Agent\Integrations\IntegrationManager;
use App\Agent\Memory\MemoryManager;
use App\Agent\Planning\Planner;
use App\Agent\Reflection\Reflector;
use App\Agent\Tools\ToolRegistry;
use App\Agent\Verification\Verifier;
use App\Models\AgentApproval;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use App\Agent\Policy\PolicyEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Loop agent: PERCEIVE → UNDERSTAND → PLAN → ACT → OBSERVE → VERIFY →
 * REFLECT → LEARN.
 *
 * Loop berjalan per-giliran dengan anggaran langkah terbatas dan menyimpan
 * seluruh state ke basis data setiap langkah. Akibatnya pekerjaan panjang
 * tidak bergantung pada satu request HTTP, dapat dijeda, dilanjutkan, atau
 * diambil alih proses lain tanpa kehilangan konteks.
 */
class AgentRuntime
{
    public function __construct(
        private readonly Planner $planner,
        private readonly Executor $executor,
        private readonly Verifier $verifier,
        private readonly Reflector $reflector,
        private readonly PolicyEngine $policy,
        private readonly MemoryManager $memory,
        private readonly ApprovalService $approvals,
        private readonly EventRecorder $events,
        private readonly ToolRegistry $registry,
        private readonly RecoveryPlanner $recovery,
        private readonly ErrorClassifier $classifier,
        private readonly IntegrationManager $integrations,
        private readonly AgentNotifier $notifier,
    ) {
    }

    /**
     * Menjalankan pekerjaan sejauh yang bisa dilakukan sekarang, lalu berhenti
     * pada keadaan yang jelas: selesai, gagal, menunggu persetujuan, menunggu
     * akses, atau kehabisan anggaran langkah untuk giliran ini.
     */
    public function tick(AgentTask $task): AgentTask
    {
        $lock = Cache::lock('agent:task:' . $task->id, 300);

        if (! $lock->get()) {
            return $task; // Proses lain sedang mengerjakan pekerjaan yang sama.
        }

        try {
            return $this->run($task);
        } finally {
            $lock->release();
        }
    }

    private function run(AgentTask $task): AgentTask
    {
        $budget = max(1, (int) config('agent.limits.steps_per_tick', 12));

        while ($budget-- > 0) {
            $task->refresh();

            if ($task->isTerminal() || $task->status === AgentTask::PAUSED) {
                return $task;
            }

            $continue = match ($task->status) {
                AgentTask::PENDING, AgentTask::PLANNING => $this->preparePlan($task),
                AgentTask::WAITING_APPROVAL             => $this->resumeAfterApproval($task),
                AgentTask::EXECUTING                    => $this->advance($task),
                AgentTask::VERIFYING                    => $this->finalize($task),
                default                                 => false,
            };

            if (! $continue) {
                return $task->refresh();
            }
        }

        $this->events->record($task, EventType::TASK_PAUSED,
            'Anggaran langkah untuk giliran ini habis; pekerjaan dilanjutkan pada giliran berikutnya.');

        return $task->refresh();
    }

    // ── UNDERSTAND & PLAN ─────────────────────────────────────────────────

    private function preparePlan(AgentTask $task): bool
    {
        $task->fill([
            'status'     => AgentTask::PLANNING,
            'started_at' => $task->started_at ?? now(),
        ])->save();

        if ($task->task_type === 'general' || ! $task->expected_output) {
            $this->planner->understand($task);
        }

        $memories = $this->memory->recall($task);

        $this->events->record($task, EventType::MEMORY_RETRIEVED,
            $memories->isEmpty()
                ? 'Belum ada pengalaman relevan — pekerjaan ini dikerjakan dari awal.'
                : sprintf('Mengambil %d pengalaman, %d pelajaran, %d prosedur relevan.',
                    $memories->experiences->count(), $memories->lessons->count(), $memories->procedures->count()),
            $memories->summary());

        $task->experience_ids = $memories->experiences->pluck('item.id')->all();
        $task->save();

        $plan = $this->planner->createPlan($task, $memories);
        $this->persistPlan($task, $plan);

        $this->events->record($task, EventType::TASK_PLANNED,
            sprintf('Rencana %d langkah disusun (%s, keyakinan %.0f%%).',
                count($plan->steps), $plan->origin, $plan->confidence * 100),
            $plan->toArray());

        return true;
    }

    private function persistPlan(AgentTask $task, Plan $plan, bool $keepCompleted = false): void
    {
        $version = $task->plan_version + 1;

        if ($keepCompleted) {
            // Langkah yang sudah tuntas dipertahankan; sisanya diganti rencana baru.
            $task->steps()->whereNotIn('status', ['succeeded', 'skipped'])->delete();
        } else {
            $task->steps()->delete();
        }

        $offset = $keepCompleted ? (int) $task->steps()->max('position') + 1 : 0;
        $prefix = $version > 1 ? 'v' . $version . '_' : '';

        foreach ($plan->steps as $index => $step) {
            AgentTaskStep::create([
                'task_id'          => $task->id,
                'step_key'         => $prefix . $step->id,
                'position'         => $offset + $index,
                'plan_version'     => $version,
                'objective'        => $step->objective,
                'tool'             => $step->tool,
                'inputs'           => $this->prefixReferences($step->inputs, $prefix),
                'dependencies'     => array_map(static fn (string $d) => $prefix . $d, $step->dependencies),
                'success_criteria' => $step->successCriteria,
                'risk_level'       => $step->riskLevel,
                'status'           => 'pending',
                'max_attempts'     => (int) config('agent.limits.max_step_attempts', 3),
            ]);
        }

        $confidence = $task->confidence ?? [];
        $confidence['planning'] = $plan->confidence;

        $task->fill([
            'plan'         => $plan->toArray(),
            'plan_version' => $version,
            'confidence'   => $confidence,
            'status'       => AgentTask::EXECUTING,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function prefixReferences(array $inputs, string $prefix): array
    {
        if ($prefix === '') {
            return $inputs;
        }

        foreach (['source_step', 'left_step', 'right_step'] as $reference) {
            if (! empty($inputs[$reference])) {
                $inputs[$reference] = $prefix . $inputs[$reference];
            }
        }

        return $inputs;
    }

    // ── ACT & OBSERVE ─────────────────────────────────────────────────────

    private function advance(AgentTask $task): bool
    {
        $step = $this->nextStep($task);

        if (! $step) {
            $task->update(['status' => AgentTask::VERIFYING]);

            return true;
        }

        return $this->executeStep($task, $step);
    }

    private function nextStep(AgentTask $task): ?AgentTaskStep
    {
        $steps = $task->steps()->get();
        $done  = $steps->filter(fn (AgentTaskStep $s) => $s->isDone())->pluck('step_key')->all();

        return $steps
            ->filter(fn (AgentTaskStep $s) => $s->status === 'pending')
            ->first(fn (AgentTaskStep $s) => array_diff((array) ($s->dependencies ?? []), $done) === []);
    }

    private function executeStep(AgentTask $task, AgentTaskStep $step): bool
    {
        if (! $step->tool || ! $this->registry->has((string) $step->tool)) {
            return $this->failStep($task, $step, ToolResult::failure(
                "Tool '{$step->tool}' tidak tersedia untuk langkah ini.", 'not_found',
            ));
        }

        $definition = $this->registry->get((string) $step->tool)->describe();
        $confidence = (float) ($task->confidence['planning'] ?? 0.5);

        if (! $this->approvals->hasApproval($step)) {
            $decision = $this->policy->evaluate($task, $step, $definition, $task->user, $confidence);

            if (! $decision->allowed) {
                $this->events->record($task, EventType::POLICY_BLOCKED, $decision->reason, [
                    'tool' => $step->tool, 'risk_level' => $decision->riskLevel,
                ], $step->id);

                return $this->failStep($task, $step, ToolResult::failure($decision->reason, 'permission'), false);
            }

            if ($decision->requiresApproval) {
                $step->update(['status' => 'waiting_approval', 'risk_level' => $decision->riskLevel]);
                $this->approvals->request($task, $step, $decision->riskLevel, $decision->reason);

                $task->fill([
                    'status'            => AgentTask::WAITING_APPROVAL,
                    'approval_required' => true,
                    'approval_status'   => 'pending',
                ])->save();

                $this->notifier->approvalNeeded($task, $step, $decision->reason);

                return false;
            }
        }

        $step->fill([
            'status'     => 'running',
            'attempts'   => $step->attempts + 1,
            'started_at' => $step->started_at ?? now(),
        ])->save();

        $this->events->record($task, EventType::STEP_STARTED, "Mengerjakan: {$step->objective}", [
            'tool' => $step->tool, 'attempt' => $step->attempts,
        ], $step->id);

        $context = new ToolContext(task: $task, agent: $task->agent, step: $step, user: $task->user);
        $result  = $this->executor->execute($task, $step, $context);

        $task->increment('steps_executed');

        if (! $result->ok) {
            return $this->failStep($task, $step, $result);
        }

        return $this->observeSuccess($task, $step, $result);
    }

    private function observeSuccess(AgentTask $task, AgentTaskStep $step, ToolResult $result): bool
    {
        $step->fill([
            'output'      => $result->output,
            'observation' => $result->observation,
            'error'       => null,
            'error_class' => null,
        ])->save();

        $verification = $this->verifier->verifyStep($step);

        $step->fill([
            'verification' => $verification->toArray(),
            'confidence'   => $verification->score,
            'status'       => $verification->passed ? 'succeeded' : 'failed',
            'finished_at'  => now(),
        ])->save();

        if (! $verification->passed) {
            $this->events->record($task, EventType::VERIFICATION_FAILED,
                "Hasil {$step->step_key} belum memenuhi kriteria: " . implode('; ', $verification->issues),
                $verification->toArray(), $step->id);

            return $this->handleFailure($task, $step, ToolResult::failure(
                'Kriteria keberhasilan tidak terpenuhi: ' . implode('; ', $verification->issues),
                'verification',
            ));
        }

        $this->confirmRecovery($task, $step);
        $this->rememberIntermediate($task, $step, $result);

        $this->events->record($task, EventType::VERIFICATION_PASSED,
            "Langkah {$step->step_key} lolos pemeriksaan.", $verification->toArray(), $step->id);

        return true;
    }

    private function rememberIntermediate(AgentTask $task, AgentTaskStep $step, ToolResult $result): void
    {
        $outputs = $task->intermediate_outputs ?? [];
        $outputs[$step->step_key] = [
            'tool'        => $step->tool,
            'observation' => $result->observation,
            'artifacts'   => $result->artifacts,
        ];

        $working = $task->working_memory ?? [];
        $working['recent_observations'] = array_slice(array_merge(
            (array) ($working['recent_observations'] ?? []),
            [$step->step_key . ': ' . $result->observation],
        ), -8);

        $task->fill([
            'intermediate_outputs' => $outputs,
            'working_memory'       => $working,
            'current_step_id'      => $step->id,
        ])->save();
    }

    // ── Pemulihan kegagalan ───────────────────────────────────────────────

    private function failStep(AgentTask $task, AgentTaskStep $step, ToolResult $result, bool $recoverable = true): bool
    {
        $step->fill([
            'status'      => 'failed',
            'error'       => $result->error,
            'error_class' => $result->errorClass ?? $this->classifier->classify($result),
            'finished_at' => now(),
        ])->save();

        $task->update(['last_error' => $result->error]);

        return $recoverable
            ? $this->handleFailure($task, $step, $result)
            : $this->afterUnrecoverableStep($task, $step);
    }

    /**
     * ERROR → CLASSIFY → DIAGNOSE → RECOVER: kegagalan tidak pernah diulang
     * secara buta; strategi pemulihannya ditentukan dari jenis kesalahannya.
     */
    private function handleFailure(AgentTask $task, AgentTaskStep $step, ToolResult $result): bool
    {
        $class    = $result->errorClass ?? $this->classifier->classify($result);
        $recovery = $this->recovery->plan($step, $result, $class);

        $this->events->record($task, EventType::RECOVERY_APPLIED,
            "Kegagalan '{$class}' ditangani dengan strategi '{$recovery->strategy}': {$recovery->message}",
            ['error_class' => $class, 'strategy' => $recovery->strategy], $step->id);

        return match ($recovery->strategy) {
            'retry', 'adjust_inputs' => $this->retryStep($task, $step, $recovery),
            'request_access'         => $this->requestAccess($task, $step, $recovery),
            'replan'                 => $this->replan($task, [$recovery->message]),
            'human_review'           => $this->requestHumanReview($task, $step, $recovery->message),
            'abort'                  => $this->fail($task, $recovery->message),
            default                  => $this->escalate($task, $step, $recovery->message),
        };
    }

    private function retryStep(AgentTask $task, AgentTaskStep $step, Recovery $recovery): bool
    {
        if (! $step->canRetry()) {
            return $this->escalate($task, $step, 'Batas percobaan ulang tercapai: ' . $recovery->message);
        }

        $step->fill([
            'status' => 'pending',
            'inputs' => $recovery->inputs !== [] ? $recovery->inputs : $step->inputs,
        ])->save();

        if ($recovery->lesson) {
            // Pelajaran baru dicatat sebagai "tertunda"; ia baru dianggap
            // terbukti bila langkah ini akhirnya berhasil.
            $working = $task->working_memory ?? [];
            $working['recovery_lesson'][$step->step_key] = $recovery->lesson + ['_message' => $recovery->message];
            $task->update(['working_memory' => $working]);
        }

        return true;
    }

    private function confirmRecovery(AgentTask $task, AgentTaskStep $step): void
    {
        $working = $task->working_memory ?? [];
        $lesson  = $working['recovery_lesson'][$step->step_key] ?? null;

        if (! $lesson) {
            return;
        }

        $message = (string) ($lesson['_message'] ?? 'pemulihan berhasil');
        unset($lesson['_message'], $working['recovery_lesson'][$step->step_key]);

        $working['pending_lessons'][] = $lesson;
        $working['recoveries'][]      = $message;

        $task->update(['working_memory' => $working]);
    }

    private function requestAccess(AgentTask $task, AgentTaskStep $step, Recovery $recovery): bool
    {
        $key        = (string) $recovery->blockedOn;
        $definition = $this->integrations->definition($key);

        $this->integrations->record($task->organization_id, $key);

        $working = $task->working_memory ?? [];
        $working['blocked_on']           = $key;
        $working['blocked_alternatives'] = $recovery->alternatives;
        $working['blocked_reason']       = $recovery->message;

        $task->fill([
            'status'         => AgentTask::PAUSED,
            'paused_at'      => now(),
            'working_memory' => $working,
            'failure_reason' => 'Menunggu akses: ' . ($definition['label'] ?? $key),
        ])->save();

        $this->events->record($task, EventType::ACCESS_REQUESTED,
            'Agent meminta akses ' . ($definition['label'] ?? $key) . ' untuk melanjutkan pekerjaan.',
            [
                'integration' => $key,
                'purpose'     => $definition['purpose'] ?? null,
                'guidance'    => $definition['guidance'] ?? [],
            ], $step->id);

        $this->notifier->accessNeeded($task, $key, $definition);

        return false;
    }

    /**
     * Berhenti dan serahkan ke manusia. Dipakai ketika mengulang tindakan
     * justru berbahaya (hasil percobaan sebelumnya tidak diketahui) atau
     * ketika keyakinan agent terlalu rendah untuk berjalan sendiri.
     */
    private function requestHumanReview(AgentTask $task, ?AgentTaskStep $step, string $message): bool
    {
        $task->fill([
            'status'         => AgentTask::PAUSED,
            'paused_at'      => now(),
            'failure_reason' => 'Menunggu pemeriksaan manusia: ' . $message,
        ])->save();

        $this->events->record($task, EventType::HUMAN_REVIEW_REQUESTED, $message, [
            'step' => $step?->step_key,
        ], $step?->id);

        $this->notifier->humanReviewNeeded($task, $message);

        return false;
    }

    private function escalate(AgentTask $task, AgentTaskStep $step, string $message): bool
    {
        if ($task->replans < (int) config('agent.limits.max_replans', 3)) {
            return $this->replan($task, [$message]);
        }

        return $this->fail($task, $message);
    }

    private function afterUnrecoverableStep(AgentTask $task, AgentTaskStep $step): bool
    {
        return $this->fail($task, (string) $step->error);
    }

    /** @param array<int, string> $issues */
    private function replan(AgentTask $task, array $issues): bool
    {
        if ($task->replans >= (int) config('agent.limits.max_replans', 3)) {
            return $this->fail($task, 'Batas penyusunan ulang rencana tercapai: ' . implode('; ', $issues));
        }

        $task->increment('replans');
        $task->refresh();

        $memories = $this->memory->recall($task);
        $plan     = $this->planner->createPlan($task, $memories, $issues);

        $this->persistPlan($task, $plan, keepCompleted: true);

        $this->events->record($task, EventType::PLAN_REVISED,
            'Rencana disusun ulang (versi ' . $task->plan_version . ') setelah: ' . implode('; ', $issues),
            $plan->toArray());

        return true;
    }

    // ── Persetujuan ───────────────────────────────────────────────────────

    private function resumeAfterApproval(AgentTask $task): bool
    {
        $pending = AgentApproval::where('task_id', $task->id)->where('status', 'pending')->first();

        if ($pending) {
            if ($pending->expires_at && $pending->expires_at->isPast()) {
                $pending->update(['status' => 'expired']);
                $pending->step?->update(['status' => 'skipped']);
                $task->update(['status' => AgentTask::VERIFYING, 'approval_status' => 'expired']);

                return true;
            }

            return false; // Masih menunggu keputusan manusia.
        }

        $task->update(['status' => AgentTask::EXECUTING, 'approval_required' => false]);

        return true;
    }

    // ── VERIFY, REFLECT, LEARN ────────────────────────────────────────────

    private function finalize(AgentTask $task): bool
    {
        $this->events->record($task, EventType::VERIFICATION_STARTED, 'Memeriksa hasil akhir pekerjaan.');

        $verification = $this->verifier->verifyTask($task);

        if (! $verification->passed && $task->replans < (int) config('agent.limits.max_replans', 3)) {
            $recoverable = $task->steps()->where('status', 'failed')->exists()
                || $task->steps()->whereNotIn('status', ['succeeded', 'skipped'])->exists();

            if ($recoverable) {
                return $this->replan($task, $verification->issues);
            }
        }

        $outcome = $verification->passed
            ? AgentTask::COMPLETED
            : ($verification->score >= 0.5 ? AgentTask::COMPLETED : AgentTask::FAILED);

        return $this->conclude($task, $outcome, $verification);
    }

    private function conclude(AgentTask $task, string $status, Verification $verification): bool
    {
        $deliverables = $this->verifier->deliverables($task->steps()->get());

        $task->fill([
            'status'         => $status,
            'verification'   => $verification->toArray(),
            'deliverables'   => $deliverables,
            'final_output'   => $this->composeOutput($task, $verification),
            'finished_at'    => now(),
            'failure_reason' => $status === AgentTask::FAILED ? implode('; ', $verification->issues) : null,
        ])->save();

        $this->learn($task, $verification);

        $this->events->record(
            $task,
            $status === AgentTask::COMPLETED ? EventType::TASK_COMPLETED : EventType::TASK_FAILED,
            $status === AgentTask::COMPLETED
                ? 'Pekerjaan selesai dengan keyakinan ' . round(($task->confidence['overall'] ?? 0) * 100) . '%.'
                : 'Pekerjaan dihentikan: ' . implode('; ', $verification->issues),
            ['verification' => $verification->toArray()],
        );

        $task->refresh();

        // Hasil yang selesai tetapi keyakinannya rendah tetap diserahkan
        // hasilnya, disertai permintaan agar manusia memeriksanya.
        $threshold = (float) config('agent.policy.review_below', 0.45);

        if ($status === AgentTask::COMPLETED && $task->overallConfidence() < $threshold) {
            $this->events->record($task, EventType::HUMAN_REVIEW_REQUESTED,
                'Keyakinan hasil hanya ' . round($task->overallConfidence() * 100) . '% — mohon diperiksa manusia.',
                ['threshold' => $threshold]);
        }

        $this->notifier->taskFinished($task);

        return false;
    }

    private function learn(AgentTask $task, Verification $verification): void
    {
        $reflection = $this->reflector->reflect($task, $verification);
        $facts      = $this->reflector->facts($task, $verification);

        $confidence = $task->confidence ?? [];
        $confidence['execution']    = $facts['step_success_ratio'];
        $confidence['verification'] = $verification->score;
        $confidence['overall']      = $reflection->confidence;
        $task->update(['confidence' => $confidence]);

        $this->events->record($task, EventType::REFLECTION_CREATED, $reflection->summary, $reflection->toArray());

        // Nilai memori yang dipakai pada pekerjaan ini disesuaikan lebih dulu,
        // baru pengalaman baru disimpan — supaya bobotnya tidak bercampur.
        $this->memory->reinforce($task, $reflection->outcome);

        $experience = $this->memory->learn($task, $reflection, $facts);

        $this->events->record($task, EventType::EXPERIENCE_CREATED,
            'Pengalaman disimpan (' . $reflection->outcome . ', keyakinan ' . round($reflection->confidence * 100) . '%).',
            ['experience_id' => $experience->id, 'lessons' => count($reflection->lessons)]);

        foreach ($reflection->lessons as $lesson) {
            $this->events->record($task, EventType::LESSON_CREATED, $lesson['lesson'], [
                'recommendation' => $lesson['recommendation'] ?? null,
            ]);
        }

        // Memori kerja tidak perlu dibawa-bawa setelah pekerjaan selesai.
        $working = $task->working_memory ?? [];
        unset($working['pending_lessons'], $working['recovery_lesson']);
        $task->update(['working_memory' => $working]);
    }

    private function composeOutput(AgentTask $task, Verification $verification): string
    {
        $lines = [];

        foreach ($task->steps()->where('status', 'succeeded')->get() as $step) {
            if ($step->observation) {
                $lines[] = '• ' . $step->observation;
            }

            if (! empty($step->output['note'])) {
                $lines[] = (string) $step->output['note'];
            }
        }

        $deliverables = $this->verifier->deliverables($task->steps()->get());

        if ($deliverables !== []) {
            $lines[] = 'Berkas hasil: ' . implode(', ', array_column($deliverables, 'name'));
        }

        if ($verification->issues !== []) {
            $lines[] = 'Catatan pemeriksaan: ' . implode('; ', $verification->issues);
        }

        return Str::limit(implode("\n", $lines), 8000);
    }

    private function fail(AgentTask $task, string $reason): bool
    {
        $this->approvals->cancelPending($task);

        $verification = $this->verifier->verifyTask($task);

        $task->update(['failure_reason' => $reason]);

        return $this->conclude($task, AgentTask::FAILED, $verification);
    }

    // ── Kendali manusia ───────────────────────────────────────────────────

    public function pause(AgentTask $task, ?string $reason = null): AgentTask
    {
        if ($task->isTerminal()) {
            return $task;
        }

        $task->fill(['status' => AgentTask::PAUSED, 'paused_at' => now()])->save();
        $this->events->record($task, EventType::TASK_PAUSED, $reason ?: 'Pekerjaan dijeda oleh pengguna.');

        return $task;
    }

    public function resume(AgentTask $task): AgentTask
    {
        if ($task->status !== AgentTask::PAUSED) {
            return $task;
        }

        $working = $task->working_memory ?? [];
        unset($working['blocked_on'], $working['blocked_alternatives'], $working['blocked_reason']);

        $task->fill([
            'status'         => $task->plan ? AgentTask::EXECUTING : AgentTask::PENDING,
            'paused_at'      => null,
            'working_memory' => $working,
            'failure_reason' => null,
        ])->save();

        // Langkah yang gagal karena akses/kendala sementara dicoba lagi.
        $task->steps()->where('status', 'failed')->update(['status' => 'pending', 'error' => null]);

        $this->events->record($task, EventType::TASK_RESUMED, 'Pekerjaan dilanjutkan.');

        return $task;
    }

    public function cancel(AgentTask $task, ?string $reason = null): AgentTask
    {
        if ($task->isTerminal()) {
            return $task;
        }

        $this->approvals->cancelPending($task);

        $task->fill([
            'status'         => AgentTask::CANCELLED,
            'finished_at'    => now(),
            'failure_reason' => $reason ?: 'Dibatalkan oleh pengguna.',
        ])->save();

        $this->events->record($task, EventType::TASK_CANCELLED, $task->failure_reason);

        return $task;
    }
}
