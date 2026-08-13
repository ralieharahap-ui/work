<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * State lengkap satu pekerjaan yang ditangani agent. Seluruh kolom di sini
 * adalah sumber kebenaran runtime: agent dapat berhenti kapan saja dan
 * melanjutkan dari state ini (pause/resume, antrean, atau proses lain).
 */
class AgentTask extends Model
{
    use HasUuids;

    public const PENDING          = 'PENDING';
    public const PLANNING         = 'PLANNING';
    public const WAITING_APPROVAL = 'WAITING_APPROVAL';
    public const EXECUTING        = 'EXECUTING';
    public const VERIFYING        = 'VERIFYING';
    public const COMPLETED        = 'COMPLETED';
    public const FAILED           = 'FAILED';
    public const CANCELLED        = 'CANCELLED';
    public const PAUSED           = 'PAUSED';

    /** Status yang tidak akan berubah lagi tanpa campur tangan manusia. */
    public const TERMINAL = [self::COMPLETED, self::FAILED, self::CANCELLED];

    /** Status yang siap dilanjutkan oleh runtime pada tick berikutnya. */
    public const RUNNABLE = [self::PENDING, self::PLANNING, self::EXECUTING, self::VERIFYING];

    protected $fillable = [
        'organization_id', 'agent_id', 'user_id', 'source', 'external_ref',
        'title', 'objective', 'task_type', 'context', 'constraints', 'expected_output',
        'priority', 'deadline', 'risk_level', 'status',
        'plan', 'plan_version', 'replans', 'steps_executed', 'current_step_id',
        'working_memory', 'intermediate_outputs', 'final_output', 'deliverables',
        'confidence', 'verification', 'approval_required', 'approval_status',
        'experience_ids', 'lesson_ids', 'last_error', 'failure_reason', 'idempotency_key',
        'started_at', 'finished_at', 'paused_at',
    ];

    protected $casts = [
        'context'              => 'array',
        'constraints'          => 'array',
        'plan'                 => 'array',
        'working_memory'       => 'array',
        'intermediate_outputs' => 'array',
        'deliverables'         => 'array',
        'confidence'           => 'array',
        'verification'         => 'array',
        'experience_ids'       => 'array',
        'lesson_ids'           => 'array',
        'approval_required'    => 'boolean',
        'deadline'             => 'datetime',
        'started_at'           => 'datetime',
        'finished_at'          => 'datetime',
        'paused_at'            => 'datetime',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function agent(): BelongsTo        { return $this->belongsTo(Agent::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }

    public function steps(): HasMany      { return $this->hasMany(AgentTaskStep::class, 'task_id')->orderBy('position'); }
    public function events(): HasMany     { return $this->hasMany(AgentEvent::class, 'task_id')->orderBy('created_at'); }
    public function executions(): HasMany { return $this->hasMany(AgentToolExecution::class, 'task_id')->latest(); }
    public function approvals(): HasMany  { return $this->hasMany(AgentApproval::class, 'task_id')->latest(); }
    public function experiences(): HasMany{ return $this->hasMany(AgentExperience::class, 'task_id'); }

    public function isTerminal(): bool { return in_array($this->status, self::TERMINAL, true); }
    public function isRunnable(): bool { return in_array($this->status, self::RUNNABLE, true); }

    public function overallConfidence(): float
    {
        return (float) ($this->confidence['overall'] ?? 0.0);
    }
}
