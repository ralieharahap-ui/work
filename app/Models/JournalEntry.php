<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'entry_no', 'entry_date', 'description',
        'ref_type', 'ref_id', 'is_posted',
        'status', 'current_level', 'created_by', 'reject_reason',
    ];

    protected $casts = [
        'entry_date'    => 'date',
        'is_posted'     => 'boolean',
        'current_level' => 'integer',
    ];

    public function lines(): HasMany       { return $this->hasMany(JournalLine::class); }
    public function attachments(): HasMany { return $this->hasMany(JournalAttachment::class); }
    public function approvals(): HasMany   { return $this->hasMany(JournalApproval::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }

    public function isBalanced(): bool
    {
        $lines = $this->lines;
        return round($lines->sum('debit'), 2) === round($lines->sum('credit'), 2);
    }
}
