<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAsset extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'description', 'purchase_date', 'qty',
        'unit_cost', 'residual_value', 'useful_life_months', 'account_id', 'notes',
    ];

    protected $casts = [
        'purchase_date'  => 'date',
        'qty'            => 'integer',
        'unit_cost'      => 'decimal:2',
        'residual_value' => 'decimal:2',
        'useful_life_months' => 'integer',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function account(): BelongsTo      { return $this->belongsTo(Account::class); }

    /** Harga perolehan total = qty × harga per unit. */
    public function totalCost(): float
    {
        return (float) $this->unit_cost * (int) $this->qty;
    }

    /** Dasar penyusutan = total perolehan − nilai sisa. */
    public function depreciableBase(): float
    {
        return max(0, $this->totalCost() - (float) $this->residual_value);
    }

    /** Penyusutan per bulan (garis lurus). */
    public function monthlyDepreciation(): float
    {
        $ue = (int) $this->useful_life_months;
        if ($ue <= 0) {
            return 0;
        }
        return $this->depreciableBase() / $ue;
    }

    /**
     * Jumlah bulan penyusutan yang sudah berjalan dari tgl beli s/d akhir bulan $asOf.
     * Bulan pembelian dihitung sebagai bulan pertama penyusutan (contoh spec:
     * beli Sep 2024, per Des 2024 = 4 bulan).
     */
    public function elapsedMonths(\Carbon\CarbonInterface $asOf): int
    {
        if (! $this->purchase_date) {
            return 0;
        }
        $start = $this->purchase_date->copy()->startOfMonth();
        $end   = $asOf->copy()->startOfMonth();
        if ($end->lt($start)) {
            return 0;
        }
        $months = $start->diffInMonths($end) + 1; // inklusif bulan pembelian
        return (int) min($months, (int) $this->useful_life_months);
    }

    /** Akumulasi penyusutan s/d $asOf. */
    public function accumulatedDepreciation(\Carbon\CarbonInterface $asOf): float
    {
        return $this->monthlyDepreciation() * $this->elapsedMonths($asOf);
    }

    /** Nilai buku = total perolehan − akumulasi penyusutan. */
    public function bookValue(\Carbon\CarbonInterface $asOf): float
    {
        return $this->totalCost() - $this->accumulatedDepreciation($asOf);
    }
}
