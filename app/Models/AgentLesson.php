<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pelajaran ringkas yang dapat dipakai ulang — hasil refleksi, bukan salinan
 * percakapan. `payload` menyimpan petunjuk terstruktur (mis. pemetaan kolom)
 * yang bisa langsung disuntikkan ke rencana berikutnya.
 */
class AgentLesson extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'experience_id', 'task_type', 'scope', 'subject',
        'trigger', 'lesson', 'recommendation', 'payload', 'evidence', 'confidence',
        'use_count', 'success_count', 'failure_count', 'keywords', 'embedding',
        'is_active', 'last_used_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'evidence'     => 'array',
        'keywords'     => 'array',
        'embedding'    => 'array',
        'confidence'   => 'float',
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function experience(): BelongsTo { return $this->belongsTo(AgentExperience::class, 'experience_id'); }
}
