<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionItem extends Model
{
    use HasUuids;

    protected $fillable = ['subscription_id', 'item_id', 'description', 'qty', 'unit_price'];
    protected $casts    = ['qty' => 'decimal:3', 'unit_price' => 'decimal:2'];

    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function item(): BelongsTo         { return $this->belongsTo(Item::class); }
}
