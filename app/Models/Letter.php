<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Letter extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'division_id', 'created_by',
        'letter_no', 'type', 'direction',   // type: official|memo|decree; direction: internal|external
        'subject', 'body', 'to_recipient',
        'status',                            // draft|review|approved|sent|archived
        'approved_by', 'approved_at', 'sent_at',
        'attachment_path',
    ];

    protected $casts = ['approved_at' => 'datetime', 'sent_at' => 'datetime'];

    public function creator(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo   { return $this->belongsTo(User::class, 'approved_by'); }
    public function division(): BelongsTo   { return $this->belongsTo(Division::class); }
    public function approvals(): HasMany    { return $this->hasMany(LetterApproval::class); }
}
