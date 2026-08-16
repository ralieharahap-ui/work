<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalApproval extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['journal_entry_id', 'level', 'user_id', 'action', 'notes', 'created_at'];
    protected $casts    = ['created_at' => 'datetime'];

    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
}
