<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterApproval extends Model
{
    use HasUuids;

    public $timestamps  = false;
    protected $fillable = ['letter_id', 'user_id', 'action', 'notes', 'acted_at'];
    protected $casts    = ['acted_at' => 'datetime'];

    public function letter(): BelongsTo { return $this->belongsTo(Letter::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}
