<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Memori prosedural: kerangka langkah yang terbukti untuk satu keluarga task
 * (mis. REPORT_GENERATION_V1). Versi baru dibuat ketika kerangkanya berubah,
 * sehingga riwayat keberhasilan versi lama tetap utuh.
 */
class AgentProcedure extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'name', 'task_type', 'version', 'trigger', 'preconditions',
        'steps', 'success_rate', 'use_count', 'success_count', 'failure_count',
        'keywords', 'embedding', 'is_active', 'last_used_at',
    ];

    protected $casts = [
        'preconditions' => 'array',
        'steps'         => 'array',
        'keywords'      => 'array',
        'embedding'     => 'array',
        'success_rate'  => 'float',
        'is_active'     => 'boolean',
        'last_used_at'  => 'datetime',
    ];

    public function label(): string
    {
        return $this->name . '_V' . $this->version;
    }

    public function recomputeSuccessRate(): float
    {
        $total = $this->success_count + $this->failure_count;

        // Penghalusan Laplace: satu keberhasilan tunggal tidak langsung berarti 100%.
        return $total === 0
            ? 0.5
            : round(($this->success_count + 1) / ($total + 2), 4);
    }
}
