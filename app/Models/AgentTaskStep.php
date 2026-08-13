<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu langkah rencana beserta hasil eksekusi & pemeriksaannya. */
class AgentTaskStep extends Model
{
    use HasUuids;

    protected $fillable = [
        'task_id', 'step_key', 'position', 'plan_version', 'objective', 'tool',
        'inputs', 'dependencies', 'success_criteria', 'risk_level', 'status',
        'attempts', 'max_attempts', 'output', 'observation', 'error', 'error_class',
        'verification', 'confidence', 'idempotency_key', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'inputs'           => 'array',
        'dependencies'     => 'array',
        'success_criteria' => 'array',
        'output'           => 'array',
        'verification'     => 'array',
        'confidence'       => 'float',
        'started_at'       => 'datetime',
        'finished_at'      => 'datetime',
    ];

    public function task(): BelongsTo       { return $this->belongsTo(AgentTask::class, 'task_id'); }
    public function executions(): HasMany   { return $this->hasMany(AgentToolExecution::class, 'step_id'); }

    public function isDone(): bool      { return in_array($this->status, ['succeeded', 'skipped'], true); }
    public function canRetry(): bool    { return $this->attempts < $this->max_attempts; }
}
