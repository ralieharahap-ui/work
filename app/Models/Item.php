<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sku', 'name', 'description', 'unit', 'unit_price',
        'cost_price', 'stock_qty', 'stock_min', 'warehouse_loc', 'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_qty'  => 'decimal:3',
        'stock_min'  => 'decimal:3',
        'is_active'  => 'boolean',
    ];

    public function stockAdjustments(): HasMany { return $this->hasMany(StockAdjustment::class); }
    public function invoiceItems(): HasMany      { return $this->hasMany(InvoiceItem::class); }

    public function isLowStock(): bool { return $this->stock_qty <= $this->stock_min; }
}
