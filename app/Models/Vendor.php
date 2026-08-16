<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vendor extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'code', 'name', 'phone', 'address', 'payable_balance', 'is_active',
    ];

    protected $casts = [
        'payable_balance' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}
