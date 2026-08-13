<?php

namespace App\Agent\Data;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use App\Models\User;

/** Konteks eksekusi yang diberikan runtime kepada tool. */
class ToolContext
{
    public function __construct(
        public readonly AgentTask $task,
        public readonly Agent $agent,
        public readonly ?AgentTaskStep $step = null,
        public readonly ?User $user = null,
        public readonly bool $dryRun = false,
    ) {
    }

    public function organizationId(): string
    {
        return (string) $this->task->organization_id;
    }

    /** Direktori kerja tool berbasis berkas (relatif terhadap disk 'local'). */
    public function workspacePath(string $suffix = ''): string
    {
        $base = trim((string) config('agent.workspace', 'agent'), '/');
        $path = $base . '/tasks/' . $this->task->id;

        return $suffix === '' ? $path : $path . '/' . ltrim($suffix, '/');
    }
}
