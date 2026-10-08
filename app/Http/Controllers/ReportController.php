<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan keuangan. Penyajian mengikuti PSAK 201 dengan COA revisi PSAK 2026:
 * Pendapatan → HPP (Laba Bruto) → Beban Operasional (Laba Usaha) → Pendapatan & Beban Lainnya
 * (Laba Sebelum Pajak) → Pajak Penghasilan (Laba Bersih). Semua dari jurnal Posted.
 */
class ReportController extends Controller
{
    /** Bagian Laba Rugi: [kunci, label, sisi saldo (revenue = kredit−debet)]. */
    private const PL_SECTIONS = [
        ['pendapatan',        'Pendapatan',                 'revenue'],
        ['hpp',               'Beban Pokok Pendapatan',     'expense'],
        ['beban_operasional', 'Beban Operasional',          'expense'],
        ['pendapatan_lain',   'Pendapatan Lainnya',         'revenue'],
        ['beban_lain',        'Beban Keuangan & Lainnya',   'expense'],
        ['pajak',             'Beban Pajak Penghasilan',    'expense'],
    ];

    public function trialBalance(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $asOf  = $request->get('as_of', today()->toDateString());

        $accounts = Account::where('organization_id', $orgId)
            ->postable()
            ->whereNull('parent_id')
            ->orderBy('code')
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
        $orgId    = auth()->user()->organization_id;
        $dateFrom = $request->get('from', today()->startOfMonth()->toDateString());
        $dateTo   = $request->get('to', today()->toDateString());

        $pl = $this->profitLossData($orgId, $dateFrom, $dateTo);

        return Inertia::render('Books/ProfitLoss', [
            'sections'     => $pl['sections'],
            'summary'      => $pl['summary'],
            // Ringkasan lama tetap dikirim (kompatibel).
            'revenue'      => $pl['revenue'],
            'expense'      => $pl['expense'],
            'net'          => $pl['summary']['laba_bersih'],
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
     * Laporan Posisi Keuangan (Neraca) — aset & liabilitas dipisah lancar/tidak lancar.
     * Laba yang belum ditutup dipisah: saldo laba tahun-tahun lalu vs laba (rugi) tahun berjalan.
     */
    public function balanceSheet(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $asOf  = $request->get('as_of', today()->toDateString());

        $rows = function (string $type) use ($orgId, $asOf): Collection {
            return Account::where('organization_id', $orgId)
                ->where('is_active', true)
                ->where('type', $type)
                ->with(['lines' => fn ($q) => $q->whereHas('journalEntry',
                    fn ($q) => $q->where('is_posted', true)->where('entry_date', '<=', $asOf)
                )])
                ->orderBy('code')
                ->get()
                ->map(function ($a) use ($type) {
                    $debit  = (float) $a->lines->sum('debit');
                    $credit = (float) $a->lines->sum('credit');
                    // Aset = saldo debet; liabilitas & ekuitas = saldo kredit (akun kontra otomatis negatif).
                    $amount = $type === 'asset' ? $debit - $credit : $credit - $debit;
                    return ['code' => $a->code, 'name' => $a->name, 'fs_group' => $a->fs_group, 'amount' => $amount];
                })
                ->filter(fn ($r) => abs($r['amount']) > 0.004)
                ->values();
        };

        $assets      = $rows('asset');
        $liabilities = $rows('liability');
        $equity      = $rows('equity');

        $yearStart   = \Carbon\Carbon::parse($asOf)->startOfYear()->toDateString();
        $priorEnd    = \Carbon\Carbon::parse($yearStart)->subDay()->toDateString();
        $netIncome   = $this->profitLossData($orgId, $yearStart, $asOf)['summary']['laba_bersih'];
        $priorIncome = $this->profitLossData($orgId, '1900-01-01', $priorEnd)['summary']['laba_bersih'];

        $split = fn (Collection $c, string $currentGroup, string $currentPrefix) => [
            $c->filter(fn ($r) => $r['fs_group'] === $currentGroup || (! $r['fs_group'] && str_starts_with($r['code'], $currentPrefix)))->values(),
            $c->reject(fn ($r) => $r['fs_group'] === $currentGroup || (! $r['fs_group'] && str_starts_with($r['code'], $currentPrefix)))->values(),
        ];
        [$currentAssets, $nonCurrentAssets] = $split($assets, 'Aset Lancar', '11');
        [$currentLiab, $nonCurrentLiab]     = $split($liabilities, 'Liabilitas Lancar', '21');

        $totalAssets = $assets->sum('amount');
        $totalLiab   = $liabilities->sum('amount');
        $totalEquity = $equity->sum('amount') + $priorIncome + $netIncome;

        return Inertia::render('Books/BalanceSheet', [
            'groups' => [
                'assets'      => [
                    ['label' => 'Aset Lancar', 'rows' => $currentAssets, 'total' => $currentAssets->sum('amount')],
                    ['label' => 'Aset Tidak Lancar', 'rows' => $nonCurrentAssets, 'total' => $nonCurrentAssets->sum('amount')],
                ],
                'liabilities' => [
                    ['label' => 'Liabilitas Jangka Pendek', 'rows' => $currentLiab, 'total' => $currentLiab->sum('amount')],
                    ['label' => 'Liabilitas Jangka Panjang', 'rows' => $nonCurrentLiab, 'total' => $nonCurrentLiab->sum('amount')],
                ],
            ],
            'assets'          => $assets,
            'liabilities'     => $liabilities,
            'equity'          => $equity,
            'net_income'      => $netIncome,
            'prior_income'    => $priorIncome,
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
     * Peredaran bruto = pendapatan usaha (kelompok 4xxxx, termasuk kontra pendapatan) per bulan
     * × tarif PPh Final UMKM 0,50%. Pendapatan lainnya (bunga, laba penjualan aset) tidak dihitung.
     */
    public function grossTurnover(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $year  = (int) $request->get('year', now()->year);
        $rate  = 0.005; // 0,50%

        $revenueAccountIds = Account::where('organization_id', $orgId)
            ->where('type', 'revenue')
            ->get()
            ->filter(fn ($a) => self::plSection($a) === 'pendapatan')
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

    /** Bagian Laba Rugi sebuah akun — dari sub laporan COA, cadangan dari digit awal kode. */
    public static function plSection(Account $a): string
    {
        $g = strtolower((string) $a->fs_group);
        return match (true) {
            $g === 'pendapatan'                          => 'pendapatan',
            str_starts_with($g, 'hpp')                   => 'hpp',
            $g === 'beban operasional'                   => 'beban_operasional',
            $g === 'pendapatan lainnya'                  => 'pendapatan_lain',
            str_starts_with($g, 'beban keuangan')        => 'beban_lain',
            $g === 'pajak penghasilan'                   => 'pajak',
            default => match ($a->code[0] ?? '') {
                '4' => 'pendapatan', '5' => 'hpp', '6' => 'beban_operasional',
                '7' => 'pendapatan_lain', '8' => 'beban_lain', '9' => 'pajak',
                default => $a->type === 'revenue' ? 'pendapatan' : 'beban_operasional',
            },
        };
    }

    /** Hitung Laba Rugi berjenjang untuk satu periode. */
    private function profitLossData(string $orgId, string $from, string $to): array
    {
        $accounts = Account::where('organization_id', $orgId)
            ->whereIn('type', ['revenue', 'expense'])
            ->where('is_active', true)
            ->with(['lines' => fn ($q) => $q->whereHas('journalEntry',
                fn ($q) => $q->where('is_posted', true)->whereBetween('entry_date', [$from, $to])
            )])
            ->orderBy('code')
            ->get();

        $sections = collect(self::PL_SECTIONS)->map(function ($s) use ($accounts) {
            [$key, $label, $side] = $s;
            $rows = $accounts->filter(fn ($a) => self::plSection($a) === $key)
                ->map(fn ($a) => [
                    'code'   => $a->code,
                    'name'   => $a->name,
                    'amount' => $side === 'revenue'
                        ? (float) $a->lines->sum('credit') - (float) $a->lines->sum('debit')
                        : (float) $a->lines->sum('debit') - (float) $a->lines->sum('credit'),
                ])
                ->filter(fn ($r) => abs($r['amount']) > 0.004)
                ->values();
            return ['key' => $key, 'label' => $label, 'accounts' => $rows, 'total' => $rows->sum('amount')];
        })->keyBy('key');

        $t = fn ($k) => (float) $sections[$k]['total'];
        $labaBruto  = $t('pendapatan') - $t('hpp');
        $labaUsaha  = $labaBruto - $t('beban_operasional');
        $labaPajak  = $labaUsaha + $t('pendapatan_lain') - $t('beban_lain');
        $labaBersih = $labaPajak - $t('pajak');

        $byType = fn ($type) => $accounts->where('type', $type)->map(fn ($a) => [
            'code'   => $a->code,
            'name'   => $a->name,
            'amount' => $type === 'revenue'
                ? (float) $a->lines->sum('credit') - (float) $a->lines->sum('debit')
                : (float) $a->lines->sum('debit') - (float) $a->lines->sum('credit'),
        ])->filter(fn ($r) => abs($r['amount']) > 0.004)->values();
        $revenue = $byType('revenue');
        $expense = $byType('expense');

        return [
            'sections' => $sections->values(),
            'summary'  => [
                'laba_bruto'         => $labaBruto,
                'laba_usaha'         => $labaUsaha,
                'laba_sebelum_pajak' => $labaPajak,
                'laba_bersih'        => $labaBersih,
            ],
            'revenue'  => ['accounts' => $revenue, 'total' => $revenue->sum('amount')],
            'expense'  => ['accounts' => $expense, 'total' => $expense->sum('amount')],
        ];
    }
}
