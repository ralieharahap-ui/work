<?php

namespace App\Http\Controllers;

use App\Models\TaskProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskProjectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $this->validateProject($request);
        TaskProject::create([
            ...$data,
            'organization_id' => auth()->user()->organization_id,
        ]);

        return back()->with('success', 'Proyek baru berhasil dibuat.');
    }

    public function update(Request $request, TaskProject $taskProject): RedirectResponse
    {
        $this->authorizeManage();
        abort_if($taskProject->organization_id !== auth()->user()->organization_id, 403);

        $taskProject->update($this->validateProject($request));

        return back()->with('success', 'Proyek diperbarui.');
    }

    public function destroy(TaskProject $taskProject): RedirectResponse
    {
        $this->authorizeManage();
        abort_if($taskProject->organization_id !== auth()->user()->organization_id, 403);
        abort_unless(auth()->user()->can('tasks.delete'), 403);

        $taskProject->delete();

        return back()->with('success', 'Proyek dihapus.');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['super_admin', 'approval', 'reviewer']), 403);
    }

    private function validateProject(Request $request): array
    {
        return $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:Planning,Ongoing,On Hold,Closed',
            'owner_id'    => 'nullable|uuid|exists:users,id',
            'end_date'    => 'nullable|date',
        ]);
    }
}
