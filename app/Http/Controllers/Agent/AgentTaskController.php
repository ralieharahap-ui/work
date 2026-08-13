<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Integrations\IntegrationManager;
use App\Agent\Runtime\AgentRuntime;
use App\Agent\Runtime\AgentTaskService;
use App\Agent\Tools\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\AgentApproval;
use App\Models\AgentEvent;
use App\Models\AgentExperience;
use App\Models\AgentLesson;
use App\Models\AgentProcedure;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use App\Models\AgentToolExecution;
use App\Models\AgentToolStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dasbor asisten AI: membuat pekerjaan, memantau jalannya langkah demi
 * langkah, dan menelusuri memori yang terbentuk dari pekerjaan sebelumnya.
 */
class AgentTaskController extends Controller
{
    public function index(Request $request, IntegrationManager $integrations, ToolRegistry $registry): Response
    {
        $user  = $request->user();
        $orgId = (string) $user->organization_id;

        $tasks = AgentTask::with('user:id,name')
            ->where('organization_id', $orgId)
            ->when(! $user->hasAnyRole(['super_admin', 'approval', 'reviewer']),
                fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (AgentTask $task) => $this->summary($task));

        $selected = $this->resolveSelected($request, $orgId, $user);

        // Pengalaman memuat tujuan pekerjaan rekan kerja; anggota biasa hanya
        // melihat pengalaman dari pekerjaannya sendiri. Pelajaran, prosedur,
        // dan statistik tool bersifat pengetahuan operasional dan boleh dilihat
        // semua orang karena tidak menyebut isi pekerjaan siapa pun.
        $seesAllTasks = $user->hasAnyRole(['super_admin', 'approval', 'reviewer']);

        return Inertia::render('Agent/Index', [
            'tasks'        => $tasks,
            'task'         => $selected ? $this->detail($selected) : null,
            'integrations' => $integrations->overview($orgId),
            'onboarding'   => [
                'script'  => $integrations->onboardingScript($orgId),
                'pending' => array_column($integrations->pending($orgId), 'key'),
            ],
            'memory'       => $this->memorySnapshot($orgId, $seesAllTasks ? null : (string) $user->id),
            'tools'        => $registry->definitions(),
            'datasets'     => AgentDatasetController::catalog(),
            'agentName'    => config('agent.name'),
            'llmProvider'  => app(\App\Agent\Llm\LlmManager::class)->activeName(),
            'autonomy'     => config('agent.policy.autonomy'),
            'can'          => [
                'create'       => $user->can('agent.create'),
                'approve'      => $user->can('agent.approve'),
                'manageAccess' => $user->hasRole('super_admin'),
                'manageData'   => $user->hasAnyRole(['super_admin', 'approval', 'reviewer']),
            ],
        ]);
    }

    public function store(Request $request, AgentTaskService $service): RedirectResponse
    {
        $validated = $request->validate([
            'objective'       => ['required', 'string', 'min:5', 'max:4000'],
            'priority'        => ['nullable', 'in:Low,Medium,High,Urgent'],
            'deadline'        => ['nullable', 'date'],
            'expected_output' => ['nullable', 'string', 'max:1000'],
            'context'         => ['nullable', 'array'],
            'constraints'     => ['nullable', 'array'],
        ]);

        $task = $service->create($request->user(), $validated['objective'], [
            'priority'        => $validated['priority'] ?? 'Medium',
            'deadline'        => $validated['deadline'] ?? null,
            'expected_output' => $validated['expected_output'] ?? null,
            'context'         => $this->cleanContext($validated['context'] ?? []),
            'constraints'     => $validated['constraints'] ?? [],
        ], 'web');

        $service->run($task);

        return redirect()
            ->route('agent.index', ['task' => $task->id])
            ->with('success', 'Pekerjaan diterima asisten dan mulai dikerjakan.');
    }

    public function rerun(Request $request, AgentTask $task, AgentTaskService $service): RedirectResponse
    {
        $this->authorizeTask($request, $task);

        if ($task->isRunnable()) {
            $service->run($task);
        }

        return back()->with('success', 'Pekerjaan dilanjutkan.');
    }

    public function pause(Request $request, AgentTask $task, AgentRuntime $runtime): RedirectResponse
    {
        $this->authorizeTask($request, $task);
        $runtime->pause($task, 'Dijeda oleh ' . $request->user()->name . '.');

        return back()->with('success', 'Pekerjaan dijeda.');
    }

    public function resume(Request $request, AgentTask $task, AgentRuntime $runtime, AgentTaskService $service): RedirectResponse
    {
        $this->authorizeTask($request, $task);
        $runtime->resume($task);
        $service->run($task->refresh());

        return back()->with('success', 'Pekerjaan dilanjutkan.');
    }

    public function cancel(Request $request, AgentTask $task, AgentRuntime $runtime): RedirectResponse
    {
        $this->authorizeTask($request, $task);
        $runtime->cancel($task, 'Dibatalkan oleh ' . $request->user()->name . '.');

        return back()->with('success', 'Pekerjaan dibatalkan.');
    }

    /** Mengunduh berkas hasil kerja agent (tersimpan di ruang kerja privat). */
    public function download(Request $request, AgentTask $task, int $index): StreamedResponse
    {
        $this->authorizeTask($request, $task);

        $deliverable = ($task->deliverables ?? [])[$index] ?? abort(404);
        $path        = (string) $deliverable['path'];

        // Sandbox: hanya berkas milik task ini yang boleh diunduh.
        abort_unless(str_starts_with($path, trim((string) config('agent.workspace', 'agent'), '/') . '/tasks/' . $task->id . '/'), 403);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $deliverable['name'] ?? basename($path));
    }

    // ── Penyusun data ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function summary(AgentTask $task): array
    {
        return [
            'id'          => $task->id,
            'title'       => $task->title,
            'objective'   => $task->objective,
            'task_type'   => $task->task_type,
            'status'      => $task->status,
            'priority'    => $task->priority,
            'source'      => $task->source,
            'confidence'  => $task->confidence,
            'owner'       => $task->user?->name,
            'created_at'  => $task->created_at?->toIso8601String(),
            'finished_at' => $task->finished_at?->toIso8601String(),
            'needs_approval' => $task->status === AgentTask::WAITING_APPROVAL,
        ];
    }

    /** @return array<string, mixed> */
    private function detail(AgentTask $task): array
    {
        $task->load(['steps', 'user:id,name']);

        return $this->summary($task) + [
            'expected_output' => $task->expected_output,
            'context'         => $task->context,
            'constraints'     => $task->constraints,
            'plan'            => $task->plan,
            'plan_version'    => $task->plan_version,
            'replans'         => $task->replans,
            'final_output'    => $task->final_output,
            'failure_reason'  => $task->failure_reason,
            'verification'    => $task->verification,
            'deliverables'    => array_values(array_map(
                static fn (array $file, int $index) => [
                    'name'  => $file['name'] ?? basename($file['path']),
                    'index' => $index,
                ],
                $task->deliverables ?? [],
                array_keys($task->deliverables ?? []),
            )),
            'blocked_on'      => $task->working_memory['blocked_on'] ?? null,
            'steps' => $task->steps->map(fn (AgentTaskStep $step) => [
                'id'               => $step->id,
                'step_key'         => $step->step_key,
                'objective'        => $step->objective,
                'tool'             => $step->tool,
                'status'           => $step->status,
                'attempts'         => $step->attempts,
                'risk_level'       => $step->risk_level,
                'observation'      => $step->observation,
                'error'            => $step->error,
                'error_class'      => $step->error_class,
                'success_criteria' => $step->success_criteria,
                'verification'     => $step->verification,
                'inputs'           => $step->inputs,
            ])->values(),
            'events' => AgentEvent::where('task_id', $task->id)
                ->orderBy('created_at')->limit(200)->get()
                ->map(fn (AgentEvent $event) => [
                    'id'         => $event->id,
                    'type'       => $event->type,
                    'message'    => $event->message,
                    'payload'    => $event->payload,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])->values(),
            'executions' => AgentToolExecution::where('task_id', $task->id)
                ->latest()->limit(50)->get()
                ->map(fn (AgentToolExecution $execution) => [
                    'tool'        => $execution->tool,
                    'status'      => $execution->status,
                    'duration_ms' => $execution->duration_ms,
                    'attempt'     => $execution->attempt,
                    'error'       => $execution->error,
                    'error_class' => $execution->error_class,
                    'created_at'  => $execution->created_at?->toIso8601String(),
                ])->values(),
            'approvals' => AgentApproval::where('task_id', $task->id)->latest()->get()
                ->map(fn (AgentApproval $approval) => [
                    'id'         => $approval->id,
                    'summary'    => $approval->summary,
                    'rationale'  => $approval->rationale,
                    'tool'       => $approval->tool,
                    'risk_level' => $approval->risk_level,
                    'status'     => $approval->status,
                    'payload'    => $approval->payload,
                    'decided_by' => $approval->decider?->name,
                    'decided_at' => $approval->decided_at?->toIso8601String(),
                ])->values(),
            'memory_used' => [
                'experiences' => AgentExperience::whereIn('id', (array) ($task->experience_ids ?: []))
                    ->get(['id', 'objective', 'outcome', 'confidence', 'reusable_strategy']),
                'lessons' => AgentLesson::whereIn('id', (array) ($task->lesson_ids ?: []))
                    ->get(['id', 'lesson', 'recommendation', 'confidence']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function memorySnapshot(string $organizationId, ?string $onlyUserId = null): array
    {
        return [
            'experiences' => AgentExperience::where('organization_id', $organizationId)
                ->when($onlyUserId, fn ($q) => $q->whereHas('task', fn ($t) => $t->where('user_id', $onlyUserId)))
                ->latest()->limit(25)->get()
                ->map(fn (AgentExperience $experience) => [
                    'id'           => $experience->id,
                    'task_type'    => $experience->task_type,
                    'objective'    => $experience->objective,
                    'outcome'      => $experience->outcome,
                    'confidence'   => $experience->confidence,
                    'success_rate' => $experience->successRate(),
                    'use_count'    => $experience->use_count,
                    'strategy'     => $experience->reusable_strategy,
                    'lessons'      => $experience->lessons,
                    'created_at'   => $experience->created_at?->toIso8601String(),
                ])->values(),
            'lessons' => AgentLesson::where('organization_id', $organizationId)
                ->orderByDesc('confidence')->limit(25)->get()
                ->map(fn (AgentLesson $lesson) => [
                    'id'             => $lesson->id,
                    'task_type'      => $lesson->task_type,
                    'scope'          => $lesson->scope,
                    'subject'        => $lesson->subject,
                    'trigger'        => $lesson->trigger,
                    'lesson'         => $lesson->lesson,
                    'recommendation' => $lesson->recommendation,
                    'confidence'     => $lesson->confidence,
                    'use_count'      => $lesson->use_count,
                    'is_active'      => $lesson->is_active,
                ])->values(),
            'procedures' => AgentProcedure::where('organization_id', $organizationId)
                ->orderByDesc('success_rate')->limit(15)->get()
                ->map(fn (AgentProcedure $procedure) => [
                    'id'           => $procedure->id,
                    'name'         => $procedure->label(),
                    'task_type'    => $procedure->task_type,
                    'success_rate' => $procedure->success_rate,
                    'use_count'    => $procedure->use_count,
                    'steps'        => array_column($procedure->steps, 'tool'),
                ])->values(),
            'tool_stats' => AgentToolStat::where('organization_id', $organizationId)
                ->orderByDesc('runs')->limit(20)->get()
                ->map(fn (AgentToolStat $stat) => [
                    'tool'         => $stat->tool,
                    'task_type'    => $stat->task_type,
                    'runs'         => $stat->runs,
                    'success_rate' => $stat->successRate(),
                    'avg_ms'       => $stat->averageDurationMs(),
                    'errors'       => $stat->common_errors,
                ])->values(),
        ];
    }

    private function resolveSelected(Request $request, string $organizationId, $user): ?AgentTask
    {
        $query = AgentTask::where('organization_id', $organizationId)
            ->when(! $user->hasAnyRole(['super_admin', 'approval', 'reviewer']),
                fn ($q) => $q->where('user_id', $user->id));

        if ($request->filled('task')) {
            return $query->where('id', $request->string('task'))->first();
        }

        return $query->latest()->first();
    }

    /**
     * Konteks pekerjaan boleh diisi bebas oleh pengguna, tetapi tetap dijaga
     * agar tidak menjadi tempat menitipkan rahasia.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function cleanContext(array $context): array
    {
        $blocked = ['password', 'token', 'secret', 'api_key', 'credential'];

        return collect($context)
            ->reject(fn ($value, $key) => collect($blocked)->contains(fn ($needle) => str_contains(strtolower((string) $key), $needle)))
            ->map(fn ($value) => is_array($value) ? array_slice($value, 0, 50) : $value)
            ->take(30)
            ->all();
    }

    private function authorizeTask(Request $request, AgentTask $task): void
    {
        $user = $request->user();

        abort_unless((string) $task->organization_id === (string) $user->organization_id, 403);

        abort_unless(
            $task->user_id === $user->id || $user->hasAnyRole(['super_admin', 'approval', 'reviewer']),
            403,
            'Pekerjaan ini milik pengguna lain.',
        );
    }
}
