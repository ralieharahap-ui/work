<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'code', 'name', 'type', 'parent_id', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function parent(): BelongsTo       { return $this->belongsTo(Account::class, 'parent_id'); }
    public function children(): HasMany       { return $this->hasMany(Account::class, 'parent_id'); }
    public function lines(): HasMany          { return $this->hasMany(JournalLine::class); }
}
