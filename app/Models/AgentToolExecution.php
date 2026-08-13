<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan pemanggilan tool — termasuk kunci idempotensi anti-duplikasi. */
class AgentToolExecution extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'task_id', 'step_id', 'tool', 'task_type', 'input', 'output',
        'status', 'error', 'error_class', 'duration_ms', 'attempt', 'idempotency_key', 'replayed',
    ];

    protected $casts = [
        'input'    => 'array',
        'output'   => 'array',
        'replayed' => 'boolean',
    ];

    public function task(): BelongsTo { return $this->belongsTo(AgentTask::class, 'task_id'); }
    public function step(): BelongsTo { return $this->belongsTo(AgentTaskStep::class, 'step_id'); }
}
