<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'code', 'legacy_code', 'name', 'type', 'account_type', 'fs_group',
        'normal_balance', 'report', 'parent_id', 'is_active', 'is_header',
    ];
    protected $casts = ['is_active' => 'boolean', 'is_header' => 'boolean'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function parent(): BelongsTo       { return $this->belongsTo(Account::class, 'parent_id'); }
    public function children(): HasMany       { return $this->hasMany(Account::class, 'parent_id'); }
    public function lines(): HasMany          { return $this->hasMany(JournalLine::class); }

    /** Akun detail (bukan header) — hanya akun ini yang boleh dipakai posting jurnal. */
    public function scopePostable(Builder $q): Builder
    {
        return $q->where('is_active', true)->where(fn ($w) => $w->where('is_header', false)->orWhereNull('is_header'));
    }
}
