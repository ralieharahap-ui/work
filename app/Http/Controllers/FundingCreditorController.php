<?php

namespace App\Http\Controllers;

use App\Models\FundingCreditor;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FundingCreditorController extends Controller
{
    /**
     * Master Kreditur Pendanaan (Investor/Bank) — kewajiban pendanaan operasi,
     * terpisah dari utang usaha (Vendor). Saldo dihitung otomatis dari jurnal
     * Posted yang baris utang-nya diberi kode bantu = kode kreditur.
     */
    public function index(): Response
    {
        $orgId = auth()->user()->organization_id;

        $creditors = FundingCreditor::where('organization_id', $orgId)->orderBy('code')->get();
        $movements = $this->movementsByCode($orgId);

        $rows = $creditors->map(function ($c) use ($movements) {
            $opening = (float) $c->payable_balance;
            $mv      = (float) ($movements[$c->code] ?? 0);
            $balance = $opening + $mv;
            return [
                'id'              => $c->id,
                'code'            => $c->code,
                'name'            => $c->name,
                'category'        => $c->category,
                'phone'           => $c->phone,
                'address'         => $c->address,
                'account_no'      => $c->account_no,
                'interest_rate'   => $c->interest_rate,
                'loan_ceiling'    => $c->loan_ceiling,
                'maturity_date'   => $c->maturity_date?->toDateString(),
                'is_active'       => $c->is_active,
                'opening_balance' => $opening,
                'movement'        => $mv,
                'current_balance' => $balance,
                'due_soon'        => $c->maturity_date && $c->maturity_date->diffInDays(now(), false) > -30 && $c->maturity_date->isFuture(),
            ];
        });

        return Inertia::render('Books/Creditors', [
            'creditors'  => $rows,
            'summary'    => [
                'total_investor' => $rows->where('category', 'investor')->sum('current_balance'),
                'total_bank'     => $rows->where('category', 'bank')->sum('current_balance'),
                'due_soon_count' => $rows->where('due_soon', true)->count(),
            ],
            'can_manage' => auth()->user()->hasRole('super_admin'),
        ]);
    }

    public function show(FundingCreditor $creditor): Response
    {
        abort_unless($creditor->organization_id === auth()->user()->organization_id, 403);

        $lines = JournalLine::where('aux_code', $creditor->code)
            ->whereHas('account', fn ($q) => $q->where('account_type', 'Utang Pendanaan'))
            ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $creditor->organization_id)
                ->where('is_posted', true))
            ->with(['journalEntry:id,entry_no,entry_date,description', 'account:id,code,name'])
            ->get()
            ->sortBy(fn ($l) => [$l->journalEntry->entry_date->toDateString(), $l->journalEntry->entry_no])
            ->values();

        $running = (float) $creditor->payable_balance;
        $rows = $lines->map(function ($l) use (&$running) {
            $debit  = (float) $l->debit;
            $credit = (float) $l->credit;
            $running += $credit - $debit; // kewajiban bertambah bila kredit, berkurang bila debit
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

        return Inertia::render('Books/CreditorLedger', [
            'creditor'        => $creditor->only(['id', 'code', 'name', 'category', 'phone', 'account_no', 'interest_rate', 'maturity_date']),
            'opening_balance' => (float) $creditor->payable_balance,
            'rows'            => $rows,
            'current_balance' => $running,
            'total_debit'     => $rows->sum('debit'),
            'total_credit'    => $rows->sum('credit'),
        ]);
    }

    public function store(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        FundingCreditor::create([...$this->validated($request, $orgId), 'organization_id' => $orgId]);

        return back()->with('success', 'Kreditur pendanaan berhasil ditambahkan');
    }

    public function update(Request $request, FundingCreditor $creditor)
    {
        abort_unless($creditor->organization_id === auth()->user()->organization_id, 403);

        $creditor->update($this->validated($request, $creditor->organization_id, $creditor->id));

        return back()->with('success', 'Kreditur pendanaan berhasil diperbarui');
    }

    public function destroy(FundingCreditor $creditor)
    {
        abort_unless($creditor->organization_id === auth()->user()->organization_id, 403);

        $creditor->delete();

        return back()->with('success', 'Kreditur pendanaan dihapus');
    }

    /** Total pergerakan kewajiban (kredit - debit) per kode bantu, dari jurnal Posted (akun Utang Pendanaan). */
    private function movementsByCode(string $orgId): array
    {
        return JournalLine::selectRaw('aux_code, SUM(credit - debit) as mv')
            ->whereNotNull('aux_code')
            ->whereHas('account', fn ($q) => $q->where('account_type', 'Utang Pendanaan'))
            ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $orgId)->where('is_posted', true))
            ->groupBy('aux_code')
            ->pluck('mv', 'aux_code')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function validated(Request $request, string $orgId, ?string $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('funding_creditors', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))
                    ->ignore($ignoreId),
            ],
            'name'            => 'required|string|max:255',
            'category'        => 'required|in:investor,bank',
            'phone'           => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:255',
            'account_no'      => 'nullable|string|max:50',
            'interest_rate'   => 'nullable|numeric',
            'loan_ceiling'    => 'nullable|numeric',
            'maturity_date'   => 'nullable|date',
            'payable_balance' => 'nullable|numeric',
            'is_active'       => 'boolean',
        ]);
    }
}
