<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Memori episodik: rekaman "apa yang terjadi pada task ini" yang sudah
 * disaring dari log mentah dan diredaksi dari data sensitif.
 */
class AgentExperience extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'agent_id', 'task_id', 'task_type', 'objective', 'context',
        'plan', 'actions', 'result', 'outcome', 'errors', 'successful_patterns',
        'failed_patterns', 'lessons', 'reusable_strategy', 'tools', 'confidence',
        'use_count', 'success_count', 'failure_count', 'duration_seconds',
        'keywords', 'embedding', 'is_obsolete', 'last_used_at',
    ];

    protected $casts = [
        'context'             => 'array',
        'plan'                => 'array',
        'actions'             => 'array',
        'errors'              => 'array',
        'successful_patterns' => 'array',
        'failed_patterns'     => 'array',
        'lessons'             => 'array',
        'tools'               => 'array',
        'keywords'            => 'array',
        'embedding'           => 'array',
        'confidence'          => 'float',
        'is_obsolete'         => 'boolean',
        'last_used_at'        => 'datetime',
    ];

    public function task(): BelongsTo    { return $this->belongsTo(AgentTask::class, 'task_id'); }
    public function lessonRecords(): HasMany { return $this->hasMany(AgentLesson::class, 'experience_id'); }

    /**
     * Tingkat keberhasilan saat pengalaman ini dipakai ulang. Sebelum pernah
     * dipakai, hasil task aslinya yang menjadi dasar penilaian.
     */
    public function successRate(): float
    {
        $used = $this->success_count + $this->failure_count;

        if ($used === 0) {
            return match ($this->outcome) {
                'SUCCESS' => 1.0,
                'PARTIAL' => 0.6,
                default   => 0.2,
            };
        }

        return round($this->success_count / $used, 4);
    }
}
