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

        // Pisahkan beban jadi Biaya Langsung/Direct Cost terkait proyek (HPP,
        // transport, handling, tenaga ahli, material proyek, dst.) dan Biaya
        // Tetap/OPEX (gaji kantor, sewa, penyusutan, dst.).
        // Klasifikasi utama pakai Kelompok FS (fs_group='COGS'); untuk akun
        // legacy tanpa fs_group, hanya kode 4-digit murni 5xxx = direct
        // (akun legacy `5-5xxx` bersifat OPEX → masuk biaya tetap).
        // fs_group diawali "COGS" mencakup 'COGS' & 'COGS / Contract Cost' (5299).
        $isDirect = function ($a) {
            if (! blank($a['fs_group'] ?? null)) {
                return str_starts_with($a['fs_group'], 'COGS');
            }
            return (bool) preg_match('/^5\d{3}$/', (string) $a['code']);
        };
        $direct = collect($expense['accounts'])->filter($isDirect)->values();
        $fixed  = collect($expense['accounts'])->reject($isDirect)->values();

        $directTotal = $direct->sum('amount');
        $fixedTotal  = $fixed->sum('amount');
        $grossProfit = $revenue['total'] - $directTotal;

        return Inertia::render('Books/ProfitLoss', [
            'revenue'      => $revenue,
            'direct_cost'  => ['accounts' => $direct, 'total' => $directTotal],
            'fixed_cost'   => ['accounts' => $fixed,  'total' => $fixedTotal],
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $revenue['total'] > 0 ? round($grossProfit / $revenue['total'] * 100, 1) : null,
            'net'          => $grossProfit - $fixedTotal,
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

    /**
     * Kontrol Pajak (Tahap 4 automasi PDF: Tax control).
     * Rekonsiliasi PPN Masukan/Keluaran & PPh (terutang vs dibayar dimuka),
     * per bulan, dari jurnal yang telah dirilis. KPI: selisih PPN kurang bayar
     * transparan, saldo PPh terutang yang belum disetor terlihat.
     */
    public function taxControl(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $year  = (int) $request->get('year', now()->year);

        // Peta kode akun pajak restated → sisi normal untuk perhitungan saldo.
        $map = [
            '1501' => 'debit',  // PPN Masukan Dapat Dikreditkan (aset)
            '2201' => 'credit', // PPN Keluaran (liabilitas)
            '2202' => 'credit', // PPh 21 Terutang
            '2203' => 'credit', // PPh 22 Terutang
            '2204' => 'credit', // PPh 23 Terutang
            '2205' => 'credit', // PPh Final 4(2) Terutang
            '1502' => 'debit',  // PPh 22 Dibayar Dimuka (kredit pajak)
            '1503' => 'debit',  // PPh 23 Dibayar Dimuka (kredit pajak)
        ];

        $accounts = Account::where('organization_id', $orgId)
            ->whereIn('code', array_keys($map))
            ->get(['id', 'code', 'name'])
            ->keyBy('code');

        $idToCode = $accounts->mapWithKeys(fn ($a) => [$a->id => $a->code]);

        // Saldo bulanan (index 1..12) per kode akun pajak.
        $monthly = array_fill_keys(array_keys($map), array_fill(1, 12, 0.0));

        $lines = JournalLine::whereIn('account_id', $idToCode->keys())
            ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true)->whereYear('entry_date', $year))
            ->with('journalEntry:id,entry_date')
            ->get();

        foreach ($lines as $line) {
            $code = $idToCode[$line->account_id] ?? null;
            if (! $code) continue;
            $month  = (int) $line->journalEntry->entry_date->format('n');
            $signed = $map[$code] === 'debit'
                ? (float) $line->debit - (float) $line->credit
                : (float) $line->credit - (float) $line->debit;
            $monthly[$code][$month] += $signed;
        }

        $rowFor = fn (string $code) => [
            'code' => $code,
            'name' => $accounts[$code]->name ?? $code,
            'months' => array_values($monthly[$code]),
            'total'  => array_sum($monthly[$code]),
        ];

        // Susunan PPN per bulan: Keluaran − Masukan = kurang/(lebih) bayar.
        $ppnMonthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $masukan  = $monthly['1501'][$m];
            $keluaran = $monthly['2201'][$m];
            $ppnMonthly[] = [
                'month'    => $m,
                'label'    => \Carbon\Carbon::create($year, $m, 1)->translatedFormat('M'),
                'masukan'  => $masukan,
                'keluaran' => $keluaran,
                'kurang_bayar' => $keluaran - $masukan,
            ];
        }

        return Inertia::render('Books/TaxControl', [
            'year' => $year,
            'ppn'  => [
                'monthly'        => $ppnMonthly,
                'total_masukan'  => array_sum($monthly['1501']),
                'total_keluaran' => array_sum($monthly['2201']),
                'total_kurang_bayar' => array_sum($monthly['2201']) - array_sum($monthly['1501']),
            ],
            'pph_terutang' => array_map($rowFor, ['2202', '2203', '2204', '2205']),
            'pph_dimuka'   => array_map($rowFor, ['1502', '1503']),
        ]);
    }

    /**
     * Dashboard Manajemen (Tahap 6 automasi PDF: Management reporting).
     * Ringkasan P&L, posisi neraca ringkas, & margin per segmen pendapatan,
     * dari jurnal yang telah dirilis (YTD s/d asOf).
     */
    public function dashboard(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $year  = (int) $request->get('year', now()->year);
        $from  = \Carbon\Carbon::create($year, 1, 1)->startOfYear()->toDateString();
        $to    = \Carbon\Carbon::create($year, 12, 31)->endOfYear()->toDateString();

        // Saldo neto per kode akun (menghormati sisi normal) untuk periode.
        $bal = $this->balancesByCode($orgId, $from, $to);
        $sumCodes = fn (array $codes) => collect($codes)->sum(fn ($c) => $bal[$c] ?? 0.0);

        $revenue = $sumCodes(['4101', '4102', '4103']);
        $otherIncome = $sumCodes(['4201']);
        $cogs    = $sumCodes(['5101', '5201', '5202', '5203', '5204', '5205', '5206', '5207', '5208', '5209', '5299']);
        $opex    = $sumCodes(['6101', '6102', '6103', '6104', '6105', '6106', '6107', '6108', '6110', '6111', '6112']);
        $otherExpense = $sumCodes(['6201']);

        $grossProfit = $revenue - $cogs;
        $netIncome   = $grossProfit + $otherIncome - $opex - $otherExpense;

        // Margin per segmen pendapatan vs biaya langsung terkait.
        $segments = [
            ['name' => 'Trading',  'revenue' => $sumCodes(['4101']), 'cogs' => $sumCodes(['5101'])],
            ['name' => 'Biomassa', 'revenue' => $sumCodes(['4102']), 'cogs' => $sumCodes(['5201', '5202', '5203', '5204', '5205', '5206'])],
            ['name' => 'Proyek Lumpsum', 'revenue' => $sumCodes(['4103']), 'cogs' => $sumCodes(['5207', '5208', '5209', '5299'])],
        ];
        $segments = array_map(function ($s) {
            $s['margin'] = $s['revenue'] - $s['cogs'];
            $s['margin_pct'] = $s['revenue'] > 0 ? round($s['margin'] / $s['revenue'] * 100, 1) : null;
            return $s;
        }, $segments);

        // Posisi neraca ringkas (saldo kumulatif s/d akhir tahun).
        $balAll = $this->balancesByCode($orgId, '1900-01-01', $to);
        $sumAll = fn (array $codes) => collect($codes)->sum(fn ($c) => $balAll[$c] ?? 0.0);
        $cash   = $sumAll(['1101', '1102', '1103']);
        $receivable = $sumAll(['1201', '1202', '1203']);
        $payable    = $sumAll(['2101', '2102']);

        return Inertia::render('Books/Dashboard', [
            'year' => $year,
            'pnl'  => [
                'revenue'       => $revenue,
                'other_income'  => $otherIncome,
                'cogs'          => $cogs,
                'gross_profit'  => $grossProfit,
                'opex'          => $opex,
                'other_expense' => $otherExpense,
                'net_income'    => $netIncome,
                'gross_margin_pct' => $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : null,
            ],
            'segments' => $segments,
            'position' => [
                'cash'       => $cash,
                'receivable' => $receivable,
                'payable'    => $payable,
            ],
        ]);
    }

    /**
     * Saldo neto per kode akun (debit-credit untuk aset/beban, credit-debit
     * untuk liabilitas/ekuitas/pendapatan) dari jurnal posted dalam rentang.
     * @return array<string,float>
     */
    private function balancesByCode(string $orgId, string $from, string $to): array
    {
        $accounts = Account::where('organization_id', $orgId)
            ->with(['lines' => fn ($q) => $q->whereHas('journalEntry',
                fn ($q) => $q->where('is_posted', true)->whereBetween('entry_date', [$from, $to])
            )])
            ->get(['id', 'code', 'type']);

        $out = [];
        foreach ($accounts as $a) {
            $debit  = (float) $a->lines->sum('debit');
            $credit = (float) $a->lines->sum('credit');
            $out[$a->code] = in_array($a->type, ['asset', 'expense'], true)
                ? $debit - $credit
                : $credit - $debit;
        }
        return $out;
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
                'code'     => $a->code,
                'name'     => $a->name,
                'fs_group' => $a->fs_group,
                'amount'   => $type === 'revenue'
                    ? $a->lines->sum('credit') - $a->lines->sum('debit')
                    : $a->lines->sum('debit')  - $a->lines->sum('credit'),
            ]);

        return ['accounts' => $accounts, 'total' => $accounts->sum('amount')];
    }
}
