<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function trialBalance(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $asOf  = $request->get('as_of', today()->toDateString());

        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['lines' => fn($q) => $q->whereHas('journalEntry',
                fn($q) => $q->where('is_posted', true)->where('entry_date', '<=', $asOf)
            )])
            ->get()
            ->map(fn($account) => [
                'code'   => $account->code,
                'name'   => $account->name,
                'type'   => $account->type,
                'debit'  => $account->lines->sum('debit'),
                'credit' => $account->lines->sum('credit'),
            ]);

        $totalDebit  = $accounts->sum('debit');
        $totalCredit = $accounts->sum('credit');

        return Inertia::render('Books/TrialBalance', [
            'accounts'     => $accounts,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced'  => round($totalDebit, 2) === round($totalCredit, 2),
            'as_of'        => $asOf,
        ]);
    }

    public function profitLoss(Request $request): Response
    {
        $orgId     = auth()->user()->organization_id;
        $dateFrom  = $request->get('from', today()->startOfMonth()->toDateString());
        $dateTo    = $request->get('to', today()->toDateString());

        $revenue = $this->sumType($orgId, 'revenue', $dateFrom, $dateTo);
        $expense = $this->sumType($orgId, 'expense', $dateFrom, $dateTo);

        return Inertia::render('Books/ProfitLoss', [
            'revenue'      => $revenue,
            'expense'      => $expense,
            'net'          => $revenue['total'] - $expense['total'],
            'period_from'  => $dateFrom,
            'period_to'    => $dateTo,
        ]);
    }

    private function sumType(string $orgId, string $type, string $from, string $to): array
    {
        $accounts = Account::where('organization_id', $orgId)
            ->where('type', $type)
            ->where('is_active', true)
            ->with(['lines' => fn($q) => $q->whereHas('journalEntry',
                fn($q) => $q->where('is_posted', true)
                             ->whereBetween('entry_date', [$from, $to])
            )])
            ->get()
            ->map(fn($a) => [
                'code'   => $a->code,
                'name'   => $a->name,
                'amount' => $type === 'revenue'
                    ? $a->lines->sum('credit') - $a->lines->sum('debit')
                    : $a->lines->sum('debit')  - $a->lines->sum('credit'),
            ]);

        return ['accounts' => $accounts, 'total' => $accounts->sum('amount')];
    }
}
