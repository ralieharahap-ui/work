<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingCycle extends Model
{
    use HasUuids;

    protected $fillable = [
        'subscription_id', 'invoice_id', 'period_start', 'period_end',
        'status', 'amount', 'retry_count', 'next_retry_at',
    ];

    protected $casts = [
        'period_start'  => 'date',
        'period_end'    => 'date',
        'next_retry_at' => 'datetime',
        'amount'        => 'decimal:2',
    ];

    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function invoice(): BelongsTo      { return $this->belongsTo(Invoice::class); }
}
