<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationScenario extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'name',
        'volume', 'price_cangkang', 'transport', 'via_water', 'tongkang',
        'handling', 'muat', 'bongkar', 'price_customer', 'target_margin',
        'total_cost', 'total_revenue', 'total_margin', 'margin_pct', 'notes',
    ];

    protected $casts = [
        'volume'         => 'decimal:2',
        'price_cangkang' => 'decimal:2',
        'transport'      => 'decimal:2',
        'via_water'      => 'boolean',
        'tongkang'       => 'decimal:2',
        'handling'       => 'decimal:2',
        'muat'           => 'decimal:2',
        'bongkar'        => 'decimal:2',
        'price_customer' => 'decimal:2',
        'target_margin'  => 'decimal:2',
        'total_cost'     => 'decimal:2',
        'total_revenue'  => 'decimal:2',
        'total_margin'   => 'decimal:2',
        'margin_pct'     => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
