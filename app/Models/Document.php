<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'type', 'number', 'doc_date', 'status',
        'released_by', 'released_at', 'meta', 'ref_type', 'ref_id', 'notes',
    ];

    protected $casts = [
        'doc_date'    => 'date',
        'released_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function releaser(): BelongsTo     { return $this->belongsTo(User::class, 'released_by'); }
}
