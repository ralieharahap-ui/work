<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PawmPLTU extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pawm_pltus';

    protected $fillable = [
        'name', 'province', 'city', 'latitude', 'longitude',
        'capacity', 'fuel_type', 'operator', 'status', 'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'capacity' => 'decimal:2',
    ];

    public function getCoordinates(): array
    {
        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }
}
