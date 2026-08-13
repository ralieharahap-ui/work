<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;
use App\Models\Task;

/** Rekapitulasi papan tugas: jumlah per status, prioritas, dan tunggakan. */
class TaskReportTool extends BaseTool
{
    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'tasks.report',
            title: 'Rekap tugas',
            description: 'Menghitung ringkasan tugas organisasi per status, prioritas, dan keterlambatan.',
            inputSchema: ['division' => ['type' => 'string', 'description' => 'Batasi ke satu divisi (nama)']],
            outputKeys: ['totals', 'by_status', 'by_priority', 'overdue'],
            permission: 'tasks.view',
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $tasks = Task::with('division:id,name')
            ->where('organization_id', $context->organizationId())
            ->when($input['division'] ?? null, fn ($q, $division) => $q->whereHas('division', fn ($d) => $d->where('name', 'like', '%' . $division . '%')))
            ->get();

        $byStatus   = $tasks->countBy('status')->all();
        $byPriority = $tasks->countBy('priority')->all();
        $overdue    = $tasks->filter(fn (Task $t) => $t->is_overdue);

        return ToolResult::success([
            'totals' => [
                'tasks'   => $tasks->count(),
                'done'    => $byStatus['Done'] ?? 0,
                'overdue' => $overdue->count(),
            ],
            'by_status'   => $byStatus,
            'by_priority' => $byPriority,
            'overdue'     => $overdue->take(20)->map(fn (Task $t) => [
                'title'    => $t->title,
                'deadline' => $t->deadline?->toDateString(),
                'status'   => $t->status,
            ])->values()->all(),
        ], sprintf('%d tugas, %d selesai, %d terlambat.', $tasks->count(), $byStatus['Done'] ?? 0, $overdue->count()));
    }
}
