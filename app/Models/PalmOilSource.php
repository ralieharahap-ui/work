<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalmOilSource extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'name', 'supplier_name', 'latitude', 'longitude',
        'province', 'city', 'district', 'address', 'stock_volume', 'unit',
        'stock_min', 'price', 'status', 'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'stock_volume' => 'decimal:2',
        'stock_min' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    protected $appends = ['price_incl_ppn'];

    // Harga include PPN 11%
    public function getPriceInclPpnAttribute(): float
    {
        return round((float) $this->price * 1.11, 2);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock_volume <= $this->stock_min;
    }

    public function getCoordinates(): array
    {
        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }
}
