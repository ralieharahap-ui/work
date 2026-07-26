<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'parent_id', 'name', 'code'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function parent(): BelongsTo       { return $this->belongsTo(Division::class, 'parent_id'); }
    public function children(): HasMany       { return $this->hasMany(Division::class, 'parent_id'); }
    public function users(): HasMany          { return $this->hasMany(User::class); }
}
