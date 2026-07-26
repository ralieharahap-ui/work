<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnloadingPoint extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'name', 'customer_name', 'latitude', 'longitude',
        'province', 'city', 'district', 'address', 'capacity', 'unit',
        'price', 'has_jetty', 'jetty_name', 'pic_name', 'pic_phone', 'status', 'notes',
    ];

    protected $casts = [
        'latitude'  => 'decimal:8',
        'longitude' => 'decimal:8',
        'capacity'  => 'decimal:2',
        'price'     => 'decimal:2',
        'has_jetty' => 'boolean',
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

    public function getCoordinates(): array
    {
        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }
}
