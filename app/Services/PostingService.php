<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class PostingService
{
    private function acc(string $orgId, string $code): string
    {
        return Account::where('organization_id', $orgId)->where('code', $code)->value('id')
            ?? throw new \RuntimeException("Akun {$code} belum ada di COA.");
    }

    private function generateEntryNo(): string
    {
        $prefix = 'JE-' . now()->format('Ym') . '-';
        return $prefix . str_pad(
            JournalEntry::where('entry_no', 'like', "{$prefix}%")->lockForUpdate()->count() + 1,
            4, '0', STR_PAD_LEFT
        );
    }

    public function post(string $orgId, string $desc, array $lines, string $refType, string $refId): JournalEntry
    {
        return DB::transaction(function () use ($orgId, $desc, $lines, $refType, $refId) {
            $entry = JournalEntry::create([
                'organization_id' => $orgId,
                'entry_no'        => $this->generateEntryNo(),
                'entry_date'      => today(),
                'description'     => $desc,
                'ref_type'        => $refType,
                'ref_id'          => $refId,
                'is_posted'       => true,
            ]);

            foreach ($lines as $code => $amounts) {
                $entry->lines()->create([
                    'account_id' => $this->acc($orgId, $code),
                    'debit'      => $amounts['debit']  ?? 0,
                    'credit'     => $amounts['credit'] ?? 0,
                    'memo'       => $amounts['memo']   ?? null,
                ]);
            }

            throw_unless($entry->fresh('lines')->isBalanced(), \RuntimeException::class,
                "Jurnal tidak balance untuk: {$desc}");

            return $entry;
        });
    }

    public function onInvoiceFinalized(Invoice $invoice): JournalEntry
    {
        return $this->post(
            $invoice->organization_id,
            "Penjualan {$invoice->invoice_no}",
            [
                '1-1200' => ['debit'  => $invoice->total,      'memo' => 'Piutang ' . $invoice->customer->name],
                '4-4000' => ['credit' => $invoice->subtotal],
                '2-2100' => ['credit' => $invoice->tax_amount, 'memo' => 'PPN Keluaran'],
            ],
            'invoice', $invoice->id
        );
    }

    public function onPaymentReceived(Invoice $invoice, float $cash): JournalEntry
    {
        $lines = ['1-1200' => ['credit' => $invoice->total, 'memo' => 'Pelunasan ' . $invoice->invoice_no]];

        if ($invoice->tax_mechanism === 'wapu') {
            $lines['1-1100'] = ['debit' => $cash];
            $lines['1-1400'] = ['debit' => $invoice->ppn_dipungut_wapu, 'memo' => 'Menunggu SSP/bukti pungut Wapu'];
        } else {
            $lines['1-1100'] = ['debit' => $cash];
        }

        return $this->post($invoice->organization_id, "Pelunasan {$invoice->invoice_no}",
            $lines, 'payment', $invoice->id);
    }

    public function onSspReceived(Invoice $invoice): JournalEntry
    {
        $invoice->update(['ssp_received' => true]);
        return $this->post(
            $invoice->organization_id,
            "Bukti pungut (SSP) {$invoice->invoice_no}",
            [
                '2-2100' => ['debit'  => $invoice->ppn_dipungut_wapu, 'memo' => 'Tutup utang PPN Keluaran'],
                '1-1400' => ['credit' => $invoice->ppn_dipungut_wapu],
            ],
            'ssp', $invoice->id
        );
    }
}
