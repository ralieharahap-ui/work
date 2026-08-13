<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Definisi seorang "pegawai kantor digital". Satu organisasi bisa memiliki
 * beberapa agent dengan spesialisasi berbeda di kemudian hari.
 */
class Agent extends Model
{
    use HasUuids;

    protected $table = 'agents';

    protected $fillable = [
        'organization_id', 'name', 'slug', 'role', 'persona',
        'capabilities', 'autonomy', 'settings', 'is_active',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'settings'     => 'array',
        'is_active'    => 'boolean',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function tasks(): HasMany          { return $this->hasMany(AgentTask::class, 'agent_id'); }

    /** Tool yang boleh dipakai agent ini; null/[] berarti seluruh tool terdaftar. */
    public function allowsTool(string $tool): bool
    {
        $allowed = $this->capabilities ?: [];

        return $allowed === [] || in_array($tool, $allowed, true);
    }
}
