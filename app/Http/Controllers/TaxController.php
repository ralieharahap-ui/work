<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalLine;
use App\Models\UnloadingPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    /** Kode akun kontrol PPN (COA revisi PSAK 2026 — database/data/coa_psak_2026.php). */
    private const ACC_PPN_KELUARAN = '21401';
    private const ACC_PPN_MASUKAN  = '11501';
    private const ACC_PPN_WAPU     = '11507';

    /**
     * Kontrol saldo PPN Masukan/Keluaran & clearing PPN Dipungut WAPU.
     * Saldo dihitung dari seluruh jurnal Posted s/d akhir tahun terpilih (kumulatif),
     * dengan breakdown mutasi tahun berjalan per kode bantu untuk drill-down.
     */
    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $year  = (int) $request->get('year', now()->year);

        $accounts = Account::where('organization_id', $orgId)
            ->whereIn('code', [self::ACC_PPN_KELUARAN, self::ACC_PPN_MASUKAN, self::ACC_PPN_WAPU])
            ->get()->keyBy('code');

        $ppnKeluaran = $this->accountSummary($accounts->get(self::ACC_PPN_KELUARAN), $orgId, $year);
        $ppnMasukan  = $this->accountSummary($accounts->get(self::ACC_PPN_MASUKAN), $orgId, $year);
        $ppnWapu     = $this->accountSummary($accounts->get(self::ACC_PPN_WAPU), $orgId, $year);

        $selisih = $ppnKeluaran['balance'] - $ppnMasukan['balance'];

        // Customer WAPU dengan saldo clearing (1-1400) belum nol → SSP belum dicocokkan.
        $wapuOutstanding = collect();
        if ($accounts->has(self::ACC_PPN_WAPU)) {
            $balances = JournalLine::selectRaw('aux_code, SUM(debit - credit) as bal')
                ->where('account_id', $accounts->get(self::ACC_PPN_WAPU)->id)
                ->whereNotNull('aux_code')
                ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $orgId)->where('is_posted', true))
                ->groupBy('aux_code')
                ->havingRaw('ABS(SUM(debit - credit)) > 0.01')
                ->pluck('bal', 'aux_code');

            $customers = UnloadingPoint::where('organization_id', $orgId)
                ->where('is_wapu', true)
                ->whereIn('code', $balances->keys())
                ->get(['code', 'customer_name', 'name']);

            $wapuOutstanding = $customers->map(fn ($c) => [
                'code'    => $c->code,
                'name'    => $c->customer_name ?: $c->name,
                'balance' => (float) $balances[$c->code],
            ])->values();
        }

        return Inertia::render('Books/TaxControl', [
            'year'             => $year,
            'ppn_keluaran'     => $ppnKeluaran,
            'ppn_masukan'      => $ppnMasukan,
            'ppn_wapu'         => $ppnWapu,
            'selisih'          => $selisih,
            'wapu_outstanding' => $wapuOutstanding,
            'missing_accounts' => collect([self::ACC_PPN_KELUARAN, self::ACC_PPN_MASUKAN, self::ACC_PPN_WAPU])
                ->reject(fn ($c) => $accounts->has($c))->values(),
        ]);
    }

    /** Saldo kumulatif s/d akhir tahun + breakdown mutasi tahun berjalan per kode bantu. */
    private function accountSummary(?Account $account, string $orgId, int $year): array
    {
        if (! $account) {
            return ['account' => null, 'balance' => 0.0, 'movement_year' => 0.0, 'breakdown' => []];
        }

        $isDebitNormal = $account->normal_balance !== 'Kr';

        $cumulative = JournalLine::where('account_id', $account->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $orgId)
                ->where('is_posted', true)->whereYear('entry_date', '<=', $year))
            ->selectRaw('SUM(debit) as d, SUM(credit) as c')
            ->first();

        $balance = $isDebitNormal
            ? (float) ($cumulative->d ?? 0) - (float) ($cumulative->c ?? 0)
            : (float) ($cumulative->c ?? 0) - (float) ($cumulative->d ?? 0);

        $yearLines = JournalLine::where('account_id', $account->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $orgId)
                ->where('is_posted', true)->whereYear('entry_date', $year))
            ->get();

        $movementYear = $isDebitNormal
            ? $yearLines->sum('debit') - $yearLines->sum('credit')
            : $yearLines->sum('credit') - $yearLines->sum('debit');

        $breakdown = $yearLines->groupBy(fn ($l) => $l->aux_code ?: '—')
            ->map(function ($lines, $auxCode) use ($isDebitNormal) {
                $mv = $isDebitNormal
                    ? $lines->sum('debit') - $lines->sum('credit')
                    : $lines->sum('credit') - $lines->sum('debit');
                return ['aux_code' => $auxCode, 'movement' => (float) $mv, 'count' => $lines->count()];
            })->values()->sortByDesc('movement')->values()->all();

        return [
            'account'       => ['code' => $account->code, 'name' => $account->name],
            'balance'       => round($balance, 2),
            'movement_year' => round($movementYear, 2),
            'breakdown'     => $breakdown,
        ];
    }
}
