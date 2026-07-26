<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasUuids;

    protected $fillable = ['invoice_id', 'item_id', 'description', 'qty', 'unit_price', 'discount', 'line_total'];
    protected $casts    = ['qty' => 'decimal:3', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function item(): BelongsTo    { return $this->belongsTo(Item::class); }
}
