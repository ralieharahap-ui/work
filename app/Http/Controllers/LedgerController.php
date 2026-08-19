<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    /**
     * Buku Besar (1.8) — filter Kode Akun + Periode (tahun).
     * Saldo berjalan dihitung dari jurnal yang telah dirilis (is_posted = true).
     */
    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $year  = (int) $request->get('year', now()->year);

        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'normal_balance']);

        $accountId = $request->get('account_id', $accounts->first()->id ?? null);
        $account   = $accounts->firstWhere('id', $accountId);

        $rows          = [];
        $openingBalance = 0;
        $totalDebit    = 0;
        $totalCredit   = 0;

        if ($account) {
            $isDebitNormal = $account->normal_balance !== 'Kr';

            // Saldo awal = akumulasi mutasi sebelum tahun berjalan.
            $opening = JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true)
                    ->whereYear('entry_date', '<', $year))
                ->get();
            $openingBalance = $isDebitNormal
                ? $opening->sum('debit') - $opening->sum('credit')
                : $opening->sum('credit') - $opening->sum('debit');

            $lines = JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true)
                    ->whereYear('entry_date', $year))
                ->with('journalEntry:id,entry_no,entry_date,description')
                ->get()
                ->sortBy(fn ($l) => [$l->journalEntry->entry_date->toDateString(), $l->journalEntry->entry_no])
                ->values();

            $running = $openingBalance;
            foreach ($lines as $l) {
                $debit  = (float) $l->debit;
                $credit = (float) $l->credit;
                $running += $isDebitNormal ? $debit - $credit : $credit - $debit;
                $totalDebit  += $debit;
                $totalCredit += $credit;

                $rows[] = [
                    'entry_no'    => $l->journalEntry->entry_no,
                    'entry_date'  => $l->journalEntry->entry_date->toDateString(),
                    'description' => $l->memo ?: $l->journalEntry->description,
                    'debit'       => $debit,
                    'credit'      => $credit,
                    'balance'     => $running,
                ];
            }
        }

        return Inertia::render('Books/Ledger', [
            'accounts'        => $accounts,
            'account_id'      => $accountId,
            'account'         => $account,
            'year'            => $year,
            'opening_balance' => $openingBalance,
            'rows'            => $rows,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'ending_balance'  => $openingBalance + ($account && $account->normal_balance !== 'Kr'
                                    ? $totalDebit - $totalCredit
                                    : $totalCredit - $totalDebit),
        ]);
    }
}
