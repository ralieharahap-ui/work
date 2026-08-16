<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class JournalAttachment extends Model
{
    use HasUuids;

    protected $fillable = ['journal_entry_id', 'path', 'original_name', 'mime', 'size'];

    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
