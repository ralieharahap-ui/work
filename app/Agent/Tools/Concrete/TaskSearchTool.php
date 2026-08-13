<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;
use App\Models\Task;

/** Menelusuri papan tugas organisasi — sumber data internal yang sudah ada. */
class TaskSearchTool extends BaseTool
{
    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'tasks.search',
            title: 'Cari tugas',
            description: 'Mencari tugas pada modul manajemen tugas (status, tenggat, PIC).',
            inputSchema: [
                'status'  => ['type' => 'array', 'description' => 'Daftar status, mis. ["To Do","In Progress"]'],
                'overdue' => ['type' => 'bool', 'default' => false],
                'keyword' => ['type' => 'string'],
                'pic'     => ['type' => 'string', 'description' => 'Nama PIC (pencocokan sebagian)'],
                'limit'   => ['type' => 'int', 'default' => 25],
            ],
            outputKeys: ['tasks', 'count'],
            permission: 'tasks.view',
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $query = Task::with('pic:id,name')
            ->where('organization_id', $context->organizationId())
            ->when($input['status'] ?? null, fn ($q, $statuses) => $q->whereIn('status', (array) $statuses))
            ->when($input['keyword'] ?? null, fn ($q, $keyword) => $q->where('title', 'like', '%' . $keyword . '%'))
            ->when($input['pic'] ?? null, fn ($q, $pic) => $q->whereHas('pic', fn ($p) => $p->where('name', 'like', '%' . $pic . '%')))
            ->when($input['overdue'] ?? false, fn ($q) => $q->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString())
                ->where('status', '!=', 'Done'))
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END, deadline ASC')
            ->limit(max(1, min((int) ($input['limit'] ?? 25), 100)));

        $tasks = $query->get()->map(fn (Task $task) => [
            'id'       => $task->id,
            'title'    => $task->title,
            'status'   => $task->status,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->toDateString(),
            'overdue'  => $task->is_overdue,
            'pic'      => $task->pic?->name,
        ])->all();

        return ToolResult::success(
            ['tasks' => $tasks, 'count' => count($tasks)],
            count($tasks) . ' tugas ditemukan.',
        );
    }
}
