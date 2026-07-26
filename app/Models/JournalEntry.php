<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'entry_no', 'entry_date', 'description',
        'ref_type', 'ref_id', 'is_posted',
    ];

    protected $casts = ['entry_date' => 'date', 'is_posted' => 'boolean'];

    public function lines(): HasMany { return $this->hasMany(JournalLine::class); }

    public function isBalanced(): bool
    {
        $lines = $this->lines;
        return round($lines->sum('debit'), 2) === round($lines->sum('credit'), 2);
    }
}
