<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Akses ke satu layanan luar yang diminta agent (Telegram, Microsoft 365, dsb).
 *
 * Kredensial disimpan terenkripsi dan hanya dibaca di dalam proses; nilainya
 * tidak pernah dikirim ke frontend maupun ditulis ke memori pengalaman.
 */
class AgentIntegration extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'key', 'status', 'credentials', 'meta', 'scopes',
        'granted_by', 'granted_at', 'last_verified_at', 'last_error',
    ];

    protected $casts = [
        'meta'             => 'array',
        'scopes'           => 'array',
        'granted_at'       => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function granter(): BelongsTo { return $this->belongsTo(User::class, 'granted_by'); }

    /**
     * Kredensial yang tersimpan, sudah didekripsi.
     *
     * Sengaja tidak dinamai credentials() agar tidak bentrok dengan kolom
     * bernama sama — Eloquent akan menyangka metode itu sebuah relasi.
     *
     * @return array<string, string>
     */
    public function secrets(): array
    {
        $encrypted = $this->attributes['credentials'] ?? null;

        if (! $encrypted) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString((string) $encrypted), true);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, string> $values */
    public function setSecrets(array $values): void
    {
        $values = array_filter($values, static fn ($v) => $v !== null && $v !== '');

        $this->attributes['credentials'] = $values === []
            ? null
            : Crypt::encryptString((string) json_encode($values));
    }

    public function hasSecrets(): bool
    {
        return ! empty($this->attributes['credentials']);
    }

    public function credential(string $key): ?string
    {
        $value = $this->secrets()[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
