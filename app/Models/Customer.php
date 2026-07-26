<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['organization_id', 'name', 'email', 'phone', 'address', 'npwp'];

    public function organization(): BelongsTo  { return $this->belongsTo(Organization::class); }
    public function invoices(): HasMany        { return $this->hasMany(Invoice::class); }
    public function subscriptions(): HasMany   { return $this->hasMany(Subscription::class); }
}
