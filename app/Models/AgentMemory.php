<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Memori semantik: pengetahuan umum yang tidak terikat satu task tertentu. */
class AgentMemory extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'kind', 'subject', 'content', 'keywords', 'embedding',
        'confidence', 'source', 'use_count', 'last_used_at',
    ];

    protected $casts = [
        'keywords'     => 'array',
        'embedding'    => 'array',
        'confidence'   => 'float',
        'last_used_at' => 'datetime',
    ];
}
