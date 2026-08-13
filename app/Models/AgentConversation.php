<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tautan antara satu percakapan chat (mis. chat Telegram) dengan akun aplikasi.
 * Tanpa tautan yang sah, agent hanya menjawab instruksi cara menautkan akun —
 * ini mencegah orang asing memerintah agent atas nama karyawan.
 */
class AgentConversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'channel', 'chat_id', 'chat_type', 'username',
        'display_name', 'is_linked', 'link_code', 'link_expires_at', 'state', 'last_message_at',
    ];

    protected $casts = [
        'is_linked'       => 'boolean',
        'state'           => 'array',
        'link_expires_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function put(string $key, mixed $value): void
    {
        $state = $this->state ?? [];
        $state[$key] = $value;
        $this->state = $state;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $state = $this->state ?? [];
        $value = $state[$key] ?? $default;
        unset($state[$key]);
        $this->state = $state;

        return $value;
    }
}
