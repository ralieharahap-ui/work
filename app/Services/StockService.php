<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function adjust(Item $item, float $qtyChange, string $reason, string $refType = 'manual', ?string $refId = null): void
    {
        DB::transaction(function () use ($item, $qtyChange, $reason, $refType, $refId) {
            $item->increment('stock_qty', $qtyChange);
            StockAdjustment::create([
                'item_id'    => $item->id,
                'qty_change' => $qtyChange,
                'reason'     => $reason,
                'ref_type'   => $refType,
                'ref_id'     => $refId,
                'created_at' => now(),
            ]);
        });
    }
}
