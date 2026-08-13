<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'triggered_by', 'channel', 'type', 'driver',
        'recipient', 'body', 'task_ids', 'status', 'error', 'reference', 'dedupe_key', 'sent_at',
    ];

    protected $casts = [
        'task_ids' => 'array',
        'sent_at'  => 'datetime',
    ];

    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function trigger(): BelongsTo      { return $this->belongsTo(User::class, 'triggered_by'); }
}
