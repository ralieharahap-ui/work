<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentComment extends Model
{
    use HasUuids;

    protected $fillable = ['document_id', 'user_id', 'body', 'kind'];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
}
