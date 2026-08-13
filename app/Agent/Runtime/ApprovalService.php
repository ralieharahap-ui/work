<?php

namespace App\Agent\Runtime;

use App\Agent\Events\EventRecorder;
use App\Agent\Events\EventType;
use App\Agent\Memory\Redactor;
use App\Models\AgentApproval;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use App\Models\User;

/** Antrean persetujuan manusia untuk tindakan berisiko. */
class ApprovalService
{
    public function __construct(
        private readonly EventRecorder $events,
        private readonly Redactor $redactor,
    ) {
    }

    public function request(AgentTask $task, AgentTaskStep $step, string $riskLevel, string $reason): AgentApproval
    {
        $existing = AgentApproval::where('step_id', $step->id)->where('status', 'pending')->first();

        if ($existing) {
            return $existing;
        }

        $approval = AgentApproval::create([
            'organization_id' => $task->organization_id,
            'task_id'         => $task->id,
            'step_id'         => $step->id,
            'tool'            => $step->tool,
            'risk_level'      => $riskLevel,
            'summary'         => $step->objective,
            'rationale'       => $reason,
            'payload'         => $this->redactor->forLogs($step->inputs ?? []),
            'status'          => 'pending',
            'expires_at'      => now()->addHours((int) config('agent.limits.approval_ttl_hours', 72)),
        ]);

        $this->events->record($task, EventType::APPROVAL_REQUESTED,
            "Menunggu persetujuan untuk: {$step->objective}", [
                'approval_id' => $approval->id,
                'tool'        => $step->tool,
                'risk_level'  => $riskLevel,
                'reason'      => $reason,
            ], $step->id);

        return $approval;
    }

    public function decide(AgentApproval $approval, bool $approved, ?User $decidedBy, string $via = 'web', ?string $note = null): AgentApproval
    {
        if ($approval->status !== 'pending') {
            return $approval;
        }

        $approval->fill([
            'status'        => $approved ? 'approved' : 'rejected',
            'decided_by'    => $decidedBy?->id,
            'decided_via'   => $via,
            'decision_note' => $note,
            'decided_at'    => now(),
        ])->save();

        $task = $approval->task;
        $step = $approval->step;

        if ($step) {
            $step->status = $approved ? 'pending' : 'skipped';
            $step->save();
        }

        if ($task) {
            $task->fill([
                'approval_required' => false,
                'approval_status'   => $approved ? 'approved' : 'rejected',
                'status'            => $approved ? AgentTask::EXECUTING : AgentTask::VERIFYING,
            ])->save();

            $this->events->record(
                $task,
                $approved ? EventType::APPROVAL_GRANTED : EventType::APPROVAL_REJECTED,
                ($approved ? 'Disetujui' : 'Ditolak') . ' oleh ' . ($decidedBy?->name ?? 'pengguna') . " lewat {$via}.",
                ['approval_id' => $approval->id, 'note' => $note],
                $step?->id,
                $decidedBy?->id,
            );
        }

        return $approval;
    }

    /** Menutup permintaan yang sudah tidak relevan (task dibatalkan). */
    public function cancelPending(AgentTask $task): void
    {
        AgentApproval::where('task_id', $task->id)->where('status', 'pending')
            ->update(['status' => 'cancelled', 'decided_at' => now()]);
    }

    public function hasApproval(AgentTaskStep $step): bool
    {
        return AgentApproval::where('step_id', $step->id)->where('status', 'approved')->exists();
    }

    public function wasRejected(AgentTaskStep $step): bool
    {
        return AgentApproval::where('step_id', $step->id)->where('status', 'rejected')->exists();
    }
}
