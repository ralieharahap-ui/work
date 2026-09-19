<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'type', 'number', 'doc_date', 'status',
        'released_by', 'released_at', 'meta', 'ref_type', 'ref_id', 'notes',
        'last_edited_by', 'last_edited_at', 'revision_count',
    ];

    protected $casts = [
        'doc_date'       => 'date',
        'released_at'    => 'datetime',
        'last_edited_at' => 'datetime',
        'meta'           => 'array',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function releaser(): BelongsTo     { return $this->belongsTo(User::class, 'released_by'); }
    public function lastEditor(): BelongsTo   { return $this->belongsTo(User::class, 'last_edited_by'); }
    public function comments(): HasMany       { return $this->hasMany(DocumentComment::class); }
}
