<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['organization_id', 'name', 'description', 'price', 'interval', 'trial_days', 'is_active'];
    protected $casts    = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class, 'plan_id'); }
}
