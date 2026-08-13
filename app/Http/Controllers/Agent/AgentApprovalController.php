<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Runtime\AgentTaskService;
use App\Agent\Runtime\ApprovalService;
use App\Http\Controllers\Controller;
use App\Models\AgentApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Keputusan manusia atas tindakan berisiko yang diajukan agent. */
class AgentApprovalController extends Controller
{
    public function decide(
        Request $request,
        AgentApproval $approval,
        ApprovalService $approvals,
        AgentTaskService $tasks,
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        abort_unless((string) $approval->organization_id === (string) $user->organization_id, 403);

        if ($approval->status !== 'pending') {
            return back()->with('error', 'Permintaan ini sudah diputuskan sebelumnya.');
        }

        $approved = $validated['decision'] === 'approve';

        $approvals->decide($approval, $approved, $user, 'web', $validated['note'] ?? null);

        $task = $approval->task;

        if ($task) {
            $tasks->run($task->refresh());
        }

        return back()->with('success', $approved
            ? 'Tindakan disetujui — asisten melanjutkan pekerjaan.'
            : 'Tindakan ditolak dan dilewati.');
    }
}
