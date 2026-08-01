<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Task;
use App\Models\TaskProject;
use App\Models\User;
use App\Services\TaskAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class TaskController extends Controller
{
    public function index(TaskAlertService $alerts): Response
    {
        $orgId = auth()->user()->organization_id;

        $tasks = Task::with(['pic:id,name,division_id', 'creator:id,name', 'project:id,title', 'checklistItems', 'comments.user:id,name'])
            ->where('organization_id', $orgId)
            ->latest('updated_at')
            ->get();

        $projects = TaskProject::with('owner:id,name')
            ->where('organization_id', $orgId)
            ->latest()
            ->get();

        $team = User::with(['division:id,name', 'roles:name'])
            ->where('organization_id', $orgId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'division_id', 'is_active'])
            ->map(fn (User $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'division_id' => $u->division_id,
                'division'    => $u->division?->name,
                'is_active'   => $u->is_active,
                'role'        => $u->getRoleNames()->first(),
            ]);

        $user = auth()->user();

        return Inertia::render('Tasks/Index', [
            'tasks'    => $tasks,
            'projects' => $projects,
            'team'     => $team,
            'divisions' => Division::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'roles'    => Role::orderBy('name')->pluck('name'),
            'alerts'   => $alerts->forUser($user),
            'currentUserId' => $user->id,
            'currentUserRole'   => $user->getRoleNames()->first(),
            'canManageTasks'    => $user->can('tasks.create') || $user->can('tasks.edit'),
            'canManageProjects' => $user->hasAnyRole(['super_admin', 'approval', 'reviewer']),
            'canDeleteTasks'    => $user->can('tasks.delete'),
            'canManageUsers'    => $user->hasRole('super_admin'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('tasks.create') || auth()->user()->can('tasks.edit'), 403);

        $data = $this->validateTask($request);
        $subtasks = $request->input('subtasks', []);

        DB::transaction(function () use ($data, $subtasks) {
            $task = Task::create([
                ...$data,
                'organization_id' => auth()->user()->organization_id,
                'created_by'      => auth()->id(),
            ]);
            $this->syncChecklist($task, $subtasks);
        });

        return back()->with('success', 'Task baru berhasil dibuat.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        abort_unless(auth()->user()->can('tasks.edit') || auth()->user()->can('tasks.create'), 403);

        $data = $this->validateTask($request);
        $subtasks = $request->input('subtasks', []);

        DB::transaction(function () use ($task, $data, $subtasks, $request) {
            $wasDone  = $task->status === 'Done';
            $willDone = ($data['status'] ?? null) === 'Done';

            // Bukti hanya diwajibkan saat task BERALIH menjadi Done. Mengedit task
            // yang sudah selesai tidak perlu mengunggah ulang dokumennya.
            if ($willDone && !$wasDone) {
                $data = $this->attachEvidence($request, $data);
            }

            // Task selesai yang dibuka kembali: bersihkan jejak penutupan agar
            // status dan metadata tidak saling bertentangan.
            if (!$willDone && $wasDone) {
                $data['closed_by'] = null;
                $data['closed_at'] = null;
            }

            $task->update($data);
            $this->syncChecklist($task, $subtasks);
        });

        return back()->with('success', 'Task berhasil diperbarui.');
    }

    /** Ganti status cepat dari Kanban (tidak boleh langsung ke Done — harus lewat endpoint close). */
    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        abort_unless(auth()->user()->can('tasks.edit') || auth()->user()->can('tasks.create'), 403);

        $data = $request->validate(['status' => 'required|in:To Do,In Progress,Review,Blocked']);
        $task->update($data);

        return back()->with('success', 'Status task diperbarui.');
    }

    /** Tutup task — WAJIB melampirkan dokumen bukti (evidence). */
    public function close(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        abort_unless(auth()->user()->can('tasks.edit') || auth()->user()->can('tasks.create'), 403);

        $request->validate([
            'evidence' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,xls',
        ], [
            'evidence.required' => 'Dokumen bukti (evidence) wajib diunggah untuk menutup task ini.',
        ]);

        $file = $request->file('evidence');
        $path = $file->store('task-evidence/' . $task->id, 'public');

        // Hapus file lama bila ada
        if ($task->evidence_path) {
            Storage::disk('public')->delete($task->evidence_path);
        }

        $task->update([
            'status'                 => 'Done',
            'evidence_path'          => $path,
            'evidence_original_name' => $file->getClientOriginalName(),
            'closed_by'              => auth()->id(),
            'closed_at'              => now(),
        ]);

        return back()->with('success', 'Task ditutup dengan bukti dokumen terlampir.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        abort_unless(auth()->user()->can('tasks.delete'), 403);
        abort_if($task->organization_id !== auth()->user()->organization_id, 403);

        if ($task->evidence_path) {
            Storage::disk('public')->delete($task->evidence_path);
        }
        $task->delete();

        return back()->with('success', 'Task dihapus.');
    }

    // ── Helpers ──────────────────────────────────────────────
    private function authorizeTaskAccess(Task $task): void
    {
        abort_if($task->organization_id !== auth()->user()->organization_id, 403);
    }

    private function validateTask(Request $request): array
    {
        return $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'required|in:Operasional,Strategis',
            'division_id' => 'nullable|uuid|exists:divisions,id',
            'pic_id'      => 'nullable|uuid|exists:users,id',
            'status'      => 'required|in:To Do,In Progress,Review,Blocked,Done',
            'priority'    => 'required|in:Low,Medium,High,Urgent',
            'deadline'    => 'nullable|date',
            'project_id'  => 'nullable|uuid|exists:task_projects,id',
        ]);
    }

    private function attachEvidence(Request $request, array $data): array
    {
        if (!$request->hasFile('evidence')) {
            abort(422, 'Dokumen bukti (evidence) wajib diunggah untuk menutup task ini.');
        }

        $request->validate([
            'evidence' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,xls',
        ]);

        $file = $request->file('evidence');
        $data['evidence_path'] = $file->store('task-evidence', 'public');
        $data['evidence_original_name'] = $file->getClientOriginalName();
        $data['closed_by'] = auth()->id();
        $data['closed_at'] = now();

        return $data;
    }

    private function syncChecklist(Task $task, array $subtasks): void
    {
        $task->checklistItems()->delete();
        foreach (array_values($subtasks) as $i => $item) {
            if (!is_array($item) || trim($item['text'] ?? '') === '') {
                continue;
            }
            $task->checklistItems()->create([
                'text'         => $item['text'],
                'is_completed' => (bool) ($item['is_completed'] ?? $item['isCompleted'] ?? false),
                'position'     => $i,
            ]);
        }
    }
}
