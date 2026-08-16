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

    /**
     * Neraca Lajur / Worksheet (1.3).
     * Neraca Saldo per akun, lalu dipisah ke kolom Laba/Rugi (LR) dan Neraca (NRC)
     * berdasarkan pemetaan report akun. Semua dari jurnal yang telah dirilis.
     */
    public function worksheet(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $asOf  = $request->get('as_of', today()->toDateString());

        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->with(['lines' => fn ($q) => $q->whereHas('journalEntry',
                fn ($q) => $q->where('is_posted', true)->where('entry_date', '<=', $asOf)
            )])
            ->orderBy('code')
            ->get()
            ->map(function ($a) {
                $debit  = (float) $a->lines->sum('debit');
                $credit = (float) $a->lines->sum('credit');
                $net    = $debit - $credit;
                $tbDebit  = $net > 0 ? $net : 0;
                $tbCredit = $net < 0 ? -$net : 0;

                // Akun Laba/Rugi jika report = LR atau type revenue/expense.
                $isLR = $a->report === 'LR' || in_array($a->type, ['revenue', 'expense'], true);

                return [
                    'code'      => $a->code,
                    'name'      => $a->name,
                    'tb_debit'  => $tbDebit,
                    'tb_credit' => $tbCredit,
                    'lr_debit'  => $isLR ? $tbDebit : 0,
                    'lr_credit' => $isLR ? $tbCredit : 0,
                    'nrc_debit' => $isLR ? 0 : $tbDebit,
                    'nrc_credit'=> $isLR ? 0 : $tbCredit,
                ];
            })
            ->filter(fn ($r) => $r['tb_debit'] > 0 || $r['tb_credit'] > 0)
            ->values();

        $sum = fn ($k) => $accounts->sum($k);

        // Laba/rugi berjalan = kredit LR − debet LR (pendapatan − beban).
        $netIncome = $sum('lr_credit') - $sum('lr_debit');

        return Inertia::render('Books/Worksheet', [
            'accounts'   => $accounts,
            'as_of'      => $asOf,
            'net_income' => $netIncome,
            'totals'     => [
                'tb_debit'  => $sum('tb_debit'),
                'tb_credit' => $sum('tb_credit'),
                'lr_debit'  => $sum('lr_debit'),
                'lr_credit' => $sum('lr_credit'),
                'nrc_debit' => $sum('nrc_debit'),
                'nrc_credit'=> $sum('nrc_credit'),
            ],
        ]);
    }

    /**
     * Neraca / Balance Sheet (1.4).
     * Aktiva vs Kewajiban + Modal, termasuk laba tahun berjalan.
     */
    public function balanceSheet(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $asOf  = $request->get('as_of', today()->toDateString());

        $group = function (array $types) use ($orgId, $asOf) {
            return Account::where('organization_id', $orgId)
                ->where('is_active', true)
                ->whereIn('type', $types)
                ->with(['lines' => fn ($q) => $q->whereHas('journalEntry',
                    fn ($q) => $q->where('is_posted', true)->where('entry_date', '<=', $asOf)
                )])
                ->orderBy('code')
                ->get()
                ->map(function ($a) use ($types) {
                    $debit  = (float) $a->lines->sum('debit');
                    $credit = (float) $a->lines->sum('credit');
                    // Aset = saldo debet; kewajiban & modal = saldo kredit.
                    $amount = in_array('asset', $types, true) ? $debit - $credit : $credit - $debit;
                    return ['code' => $a->code, 'name' => $a->name, 'amount' => $amount];
                })
                ->filter(fn ($r) => abs($r['amount']) > 0.004)
                ->values();
        };

        $assets      = $group(['asset']);
        $liabilities = $group(['liability']);
        $equity      = $group(['equity']);

        // Laba tahun berjalan (pendapatan − beban) s/d asOf.
        $revenue = $this->sumType($orgId, 'revenue', '1900-01-01', $asOf);
        $expense = $this->sumType($orgId, 'expense', '1900-01-01', $asOf);
        $netIncome = $revenue['total'] - $expense['total'];

        $totalAssets = $assets->sum('amount');
        $totalLiab   = $liabilities->sum('amount');
        $totalEquity = $equity->sum('amount') + $netIncome;

        return Inertia::render('Books/BalanceSheet', [
            'assets'          => $assets,
            'liabilities'     => $liabilities,
            'equity'          => $equity,
            'net_income'      => $netIncome,
            'total_assets'    => $totalAssets,
            'total_liab'      => $totalLiab,
            'total_equity'    => $totalEquity,
            'total_liab_equity' => $totalLiab + $totalEquity,
            'is_balanced'     => round($totalAssets, 2) === round($totalLiab + $totalEquity, 2),
            'as_of'           => $asOf,
        ]);
    }

    /**
     * Rekap Peredaran Bruto (1.10).
     * Peredaran bruto (pendapatan usaha) per bulan × tarif PPh Final UMKM 0,50%.
     */
    public function grossTurnover(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $year  = (int) $request->get('year', now()->year);
        $rate  = 0.005; // 0,50%

        $revenueAccountIds = Account::where('organization_id', $orgId)
            ->where('type', 'revenue')
            ->pluck('id');

        $months = [];
        $totalGross = 0;
        $totalTax   = 0;

        for ($m = 1; $m <= 12; $m++) {
            $from = \Carbon\Carbon::create($year, $m, 1)->startOfMonth()->toDateString();
            $to   = \Carbon\Carbon::create($year, $m, 1)->endOfMonth()->toDateString();

            $lines = JournalLine::whereIn('account_id', $revenueAccountIds)
                ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true)
                    ->whereBetween('entry_date', [$from, $to]))
                ->get();

            $gross = (float) $lines->sum('credit') - (float) $lines->sum('debit');
            $tax   = $gross * $rate;

            $totalGross += $gross;
            $totalTax   += $tax;

            $months[] = [
                'month'  => $m,
                'label'  => \Carbon\Carbon::create($year, $m, 1)->translatedFormat('F'),
                'gross'  => $gross,
                'tax'    => round($tax, 2),
            ];
        }

        return Inertia::render('Books/GrossTurnover', [
            'months'      => $months,
            'year'        => $year,
            'rate'        => $rate,
            'total_gross' => $totalGross,
            'total_tax'   => round($totalTax, 2),
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
