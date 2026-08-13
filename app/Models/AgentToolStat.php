<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Memori performa: tool mana yang paling berhasil untuk jenis task tertentu. */
class AgentToolStat extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'tool', 'task_type', 'runs', 'successes', 'failures',
        'total_duration_ms', 'common_errors', 'last_used_at',
    ];

    protected $casts = [
        'common_errors' => 'array',
        'last_used_at'  => 'datetime',
    ];

    public function successRate(): float
    {
        return $this->runs > 0 ? round($this->successes / $this->runs, 4) : 0.5;
    }

    public function averageDurationMs(): int
    {
        return $this->runs > 0 ? (int) round($this->total_duration_ms / $this->runs) : 0;
    }
}
