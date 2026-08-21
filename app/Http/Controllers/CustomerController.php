<?php

namespace App\Http\Controllers;

use App\Models\JournalLine;
use App\Models\UnloadingPoint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Master Customer (Piutang) — sumber data Titik Bongkar (UnloadingPoint).
     * Saldo piutang dihitung otomatis dari jurnal Posted yang baris piutang-nya
     * diberi kode bantu = kode customer.
     */
    public function index(): Response
    {
        $orgId = auth()->user()->organization_id;

        $customers = UnloadingPoint::where('organization_id', $orgId)->orderBy('name')->get();
        $movements = $this->movementsByCode($orgId);

        $rows = $customers->map(function ($c) use ($movements) {
            $opening = (float) $c->receivable_balance;
            $mv      = $c->code ? (float) ($movements[$c->code] ?? 0) : 0;
            return [
                'id'              => $c->id,
                'code'            => $c->code,
                'name'            => $c->customer_name ?: $c->name,
                'city'            => $c->city,
                'opening_balance' => $opening,
                'movement'        => $mv,
                'current_balance' => $opening + $mv,
            ];
        });

        return Inertia::render('Books/Customers', [
            'customers'  => $rows,
            'can_manage' => auth()->user()->hasRole('super_admin'),
        ]);
    }

    public function show(UnloadingPoint $customer): Response
    {
        abort_unless($customer->organization_id === auth()->user()->organization_id, 403);

        $rows = collect();
        $running = (float) $customer->receivable_balance;

        if ($customer->code) {
            $lines = JournalLine::where('aux_code', $customer->code)
                ->whereHas('account', fn ($q) => $q->where('account_type', 'like', 'Piutang%'))
                ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $customer->organization_id)
                    ->where('is_posted', true))
                ->with(['journalEntry:id,entry_no,entry_date,description', 'account:id,code,name'])
                ->get()
                ->sortBy(fn ($l) => [$l->journalEntry->entry_date->toDateString(), $l->journalEntry->entry_no])
                ->values();

            $rows = $lines->map(function ($l) use (&$running) {
                $debit  = (float) $l->debit;
                $credit = (float) $l->credit;
                $running += $debit - $credit; // piutang bertambah bila debit, berkurang bila kredit
                return [
                    'entry_no'    => $l->journalEntry->entry_no,
                    'entry_date'  => $l->journalEntry->entry_date->toDateString(),
                    'description' => $l->memo ?: $l->journalEntry->description,
                    'account'     => $l->account->code . ' — ' . $l->account->name,
                    'debit'       => $debit,
                    'credit'      => $credit,
                    'balance'     => $running,
                ];
            });
        }

        return Inertia::render('Books/CustomerLedger', [
            'customer'        => ['id' => $customer->id, 'code' => $customer->code, 'name' => $customer->customer_name ?: $customer->name, 'city' => $customer->city],
            'opening_balance' => (float) $customer->receivable_balance,
            'rows'            => $rows,
            'current_balance' => $running,
            'total_debit'     => $rows->sum('debit'),
            'total_credit'    => $rows->sum('credit'),
        ]);
    }

    /** Set kode bantu + saldo piutang awal (tidak mengubah field customer lainnya). */
    public function update(Request $request, UnloadingPoint $customer)
    {
        abort_unless($customer->organization_id === auth()->user()->organization_id, 403);

        $validated = $request->validate([
            'code' => [
                'nullable', 'string', 'max:30',
                Rule::unique('unloading_points', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $customer->organization_id))
                    ->ignore($customer->id),
            ],
            'receivable_balance' => 'nullable|numeric',
        ]);

        $customer->update([
            'code'               => $validated['code'] ?? null,
            'receivable_balance' => $validated['receivable_balance'] ?? 0,
        ]);

        return back()->with('success', 'Kode bantu & saldo awal customer diperbarui');
    }

    private function movementsByCode(string $orgId): array
    {
        return JournalLine::selectRaw('aux_code, SUM(debit - credit) as mv')
            ->whereNotNull('aux_code')
            ->whereHas('account', fn ($q) => $q->where('account_type', 'like', 'Piutang%'))
            ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $orgId)->where('is_posted', true))
            ->groupBy('aux_code')
            ->pluck('mv', 'aux_code')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
