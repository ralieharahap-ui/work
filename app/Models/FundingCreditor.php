<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingCreditor extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'code', 'name', 'category', 'phone', 'address',
        'account_no', 'interest_rate', 'loan_ceiling', 'maturity_date', 'payable_balance', 'is_active',
    ];

    protected $casts = [
        'interest_rate'   => 'decimal:2',
        'loan_ceiling'    => 'decimal:2',
        'payable_balance' => 'decimal:2',
        'maturity_date'   => 'date',
        'is_active'       => 'boolean',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}
