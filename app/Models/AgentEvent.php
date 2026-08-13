<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak audit: menjawab "kenapa agent melakukan tindakan ini?". */
class AgentEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'task_id', 'agent_id', 'user_id', 'step_id',
        'type', 'message', 'payload', 'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function task(): BelongsTo { return $this->belongsTo(AgentTask::class, 'task_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
