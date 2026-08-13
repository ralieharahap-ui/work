<?php

namespace App\Agent\Runtime;

use App\Agent\Events\EventRecorder;
use App\Agent\Events\EventType;
use App\Jobs\RunAgentTask;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pintu masuk pekerjaan baru dari mana pun asalnya (web, Telegram, API,
 * penjadwal) — satu tempat sehingga aturan pembuatan task tidak tercecer.
 */
class AgentTaskService
{
    public function __construct(private readonly EventRecorder $events)
    {
    }

    /** Agent bawaan organisasi; dibuat sekali lalu dipakai seterusnya. */
    public function agentFor(string $organizationId): Agent
    {
        return Agent::firstOrCreate(
            ['organization_id' => $organizationId, 'slug' => 'asisten-kantor'],
            [
                'name'     => (string) config('agent.name', 'Asisten Kantor'),
                'role'     => 'personal_office_employee',
                'persona'  => 'Asisten kantor yang teliti: merencanakan sebelum bertindak, memverifikasi hasil '
                    . 'sebelum menyatakan selesai, dan meminta persetujuan untuk tindakan yang tidak dapat ditarik kembali.',
                'autonomy' => (string) config('agent.policy.autonomy', 'balanced'),
                'is_active'=> true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, string $objective, array $attributes = [], string $source = 'web'): AgentTask
    {
        $objective = trim($objective);
        $agent     = $this->agentFor((string) $user->organization_id);

        $idempotencyKey = $attributes['idempotency_key']
            ?? ($source . ':' . hash('sha256', $user->id . '|' . Str::lower($objective) . '|' . now()->format('YmdHi')));

        $existing = AgentTask::where('organization_id', $user->organization_id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing; // Kiriman ganda (mis. tombol ditekan dua kali).
        }

        $task = AgentTask::create([
            'organization_id' => $user->organization_id,
            'agent_id'        => $agent->id,
            'user_id'         => $user->id,
            'source'          => $source,
            'external_ref'    => $attributes['external_ref'] ?? null,
            'title'           => $this->titleFrom((string) ($attributes['title'] ?? $objective)),
            'objective'       => $objective,
            'task_type'       => $attributes['task_type'] ?? 'general',
            'context'         => $attributes['context'] ?? [],
            'constraints'     => $attributes['constraints'] ?? [],
            'expected_output' => $attributes['expected_output'] ?? null,
            'priority'        => $attributes['priority'] ?? 'Medium',
            'deadline'        => $this->parseDeadline($attributes['deadline'] ?? null),
            'status'          => AgentTask::PENDING,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->events->record($task, EventType::TASK_CREATED,
            'Pekerjaan diterima dari ' . $source . ': ' . Str::limit($objective, 160),
            ['context' => $task->context, 'priority' => $task->priority], null, $user->id);

        return $task;
    }

    /** Menjalankan pekerjaan di latar; dengan QUEUE_CONNECTION=sync berjalan langsung. */
    public function run(AgentTask $task): void
    {
        RunAgentTask::dispatch($task->id);
    }

    /** Judul ringkas yang dipotong di batas kata, bukan di tengah kata. */
    private function titleFrom(string $text): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($clean) <= 70) {
            return $clean;
        }

        $cut   = mb_substr($clean, 0, 70);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space && $space > 40 ? mb_substr($cut, 0, $space) : $cut, " ,.;:-") . '…';
    }

    private function parseDeadline(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
