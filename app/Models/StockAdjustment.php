<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $fillable = ['item_id', 'qty_change', 'reason', 'ref_type', 'ref_id', 'created_at'];
    protected $casts    = ['created_at' => 'datetime', 'qty_change' => 'decimal:3'];

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
