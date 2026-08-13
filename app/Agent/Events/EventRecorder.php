<?php

namespace App\Agent\Events;

use App\Agent\Memory\Redactor;
use App\Models\AgentEvent;
use App\Models\AgentTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Perekam jejak audit. Setiap keputusan penting agent meninggalkan satu baris
 * peristiwa sehingga pertanyaan "kenapa agent melakukan ini?" selalu terjawab.
 */
class EventRecorder
{
    public function __construct(private readonly Redactor $redactor)
    {
    }

    /** @param array<string, mixed> $payload */
    public function record(
        ?AgentTask $task,
        string $type,
        string $message,
        array $payload = [],
        ?string $stepId = null,
        ?string $userId = null,
        ?string $organizationId = null,
    ): AgentEvent {
        $event = AgentEvent::create([
            'organization_id' => $organizationId ?? $task?->organization_id,
            'task_id'         => $task?->id,
            'agent_id'        => $task?->agent_id,
            'user_id'         => $userId ?? $task?->user_id,
            'step_id'         => $stepId,
            'type'            => $type,
            'message'         => Str::limit($this->redactor->text($message, false), 250),
            'payload'         => $this->redactor->forLogs($payload),
            'created_at'      => now(),
        ]);

        Log::channel(config('logging.default'))->info('agent.' . Str::lower($type), [
            'task_id' => $task?->id,
            'message' => $event->message,
        ]);

        return $event;
    }
}
