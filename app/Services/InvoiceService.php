<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private StockService   $stock,
        private PostingService $posting,
    ) {}

    public function generateNumber(string $orgId): string
    {
        $prefix = 'INV-' . now()->format('Ym') . '-';
        return $prefix . str_pad(
            Invoice::where('organization_id', $orgId)->where('invoice_no', 'like', "{$prefix}%")->lockForUpdate()->count() + 1,
            4, '0', STR_PAD_LEFT
        );
    }

    public function recalculate(Invoice $invoice): void
    {
        $subtotal = $invoice->items->sum('line_total');
        $tax      = round($subtotal * ($invoice->tax_rate / 100), 2);
        $ppnWapu  = $invoice->tax_mechanism === 'wapu' ? $tax : 0;

        $invoice->update([
            'subtotal'          => $subtotal,
            'tax_amount'        => $tax,
            'ppn_dipungut_wapu' => $ppnWapu,
            'total'             => $subtotal + $tax - $invoice->discount,
        ]);
    }

    public function finalize(Invoice $invoice, User $actor): void
    {
        throw_if(!$invoice->isEditable(), \Exception::class, 'Invoice tidak dalam status draft.');
        throw_if($invoice->items->isEmpty(), \Exception::class, 'Invoice tidak boleh kosong.');

        DB::transaction(function () use ($invoice, $actor) {
            $invoice->update(['status' => 'sent', 'sent_at' => now()]);

            foreach ($invoice->items->whereNotNull('item_id') as $line) {
                $this->stock->adjust($line->item, -$line->qty, 'sale', 'invoice_item', $line->id);
            }

            InvoiceApproval::create([
                'invoice_id' => $invoice->id,
                'user_id'    => $actor->id,
                'action'     => 'submitted',
                'acted_at'   => now(),
            ]);

            $this->posting->onInvoiceFinalized($invoice->load('customer'));
        });
    }

    public function recordPayment(Invoice $invoice, float $cash): void
    {
        DB::transaction(function () use ($invoice, $cash) {
            $invoice->increment('paid_amount', $cash);
            $invoice->refresh();

            // Wapu fix: lunas saat cash = DPP, bukan grand total
            if ($invoice->paid_amount >= $invoice->cash_expected) {
                $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            }

            $this->posting->onPaymentReceived($invoice, $cash);
        });
    }

    public function markOverdue(): int
    {
        return Invoice::where('status', 'sent')
            ->where('due_date', '<', today())
            ->update(['status' => 'overdue']);
    }
}
