<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingCreditor extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'code', 'name', 'category', 'phone', 'address',
        'account_no', 'interest_rate', 'interest_period', 'loan_ceiling', 'maturity_date', 'payable_balance', 'is_active',
    ];

    /** Kategori kreditur. 'investor' = Investor Eksternal (nilai lama dipertahankan). */
    public const CATEGORIES = [
        'bank'              => 'Bank',
        'investor'          => 'Investor Eksternal',
        'investor_internal' => 'Investor Internal (Pihak Berelasi)',
    ];

    protected $casts = [
        'interest_rate'   => 'decimal:2',
        'loan_ceiling'    => 'decimal:2',
        'payable_balance' => 'decimal:2',
        'maturity_date'   => 'date',
        'is_active'       => 'boolean',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function documents(): HasMany { return $this->hasMany(FundingCreditorDocument::class); }
}
