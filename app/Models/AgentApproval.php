<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Permintaan persetujuan manusia sebelum tindakan berisiko dijalankan. */
class AgentApproval extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'task_id', 'step_id', 'tool', 'risk_level', 'summary',
        'rationale', 'payload', 'status', 'decided_by', 'decided_via',
        'decision_note', 'decided_at', 'expires_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function task(): BelongsTo   { return $this->belongsTo(AgentTask::class, 'task_id'); }
    public function step(): BelongsTo   { return $this->belongsTo(AgentTaskStep::class, 'step_id'); }
    public function decider(): BelongsTo{ return $this->belongsTo(User::class, 'decided_by'); }

    public function isPending(): bool
    {
        return $this->status === 'pending' && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
