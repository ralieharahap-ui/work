<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'customer_id', 'plan_id', 'status',
        'current_period_start', 'current_period_end', 'trial_end',
        'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'current_period_start' => 'date',
        'current_period_end'   => 'date',
        'trial_end'            => 'date',
        'cancelled_at'         => 'datetime',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function plan(): BelongsTo     { return $this->belongsTo(BillingPlan::class, 'plan_id'); }
    public function items(): HasMany      { return $this->hasMany(SubscriptionItem::class); }
    public function cycles(): HasMany     { return $this->hasMany(BillingCycle::class); }

    public function isActive(): bool       { return in_array($this->status, ['active', 'trialing']); }
    public function isDueForBilling(): bool { return $this->isActive() && $this->current_period_end->lte(today()); }
}
