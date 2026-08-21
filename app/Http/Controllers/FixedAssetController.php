<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    /**
     * Daftar Aset (1.9) — penyusutan garis lurus per bulan.
     * Bulan pembelian dihitung sebagai bulan pertama penyusutan.
     */
    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;
        $asOf  = $request->get('as_of', today()->toDateString());
        $asOfC = \Carbon\Carbon::parse($asOf);

        $assets = FixedAsset::where('organization_id', $orgId)
            ->with('account:id,code,name')
            ->orderBy('purchase_date')
            ->get()
            ->map(fn ($a) => [
                'id'                 => $a->id,
                'description'        => $a->description,
                'purchase_date'      => $a->purchase_date?->toDateString(),
                'qty'                => $a->qty,
                'unit_cost'          => (float) $a->unit_cost,
                'total_cost'         => $a->totalCost(),
                'residual_value'     => (float) $a->residual_value,
                'useful_life_months' => $a->useful_life_months,
                'monthly'            => round($a->monthlyDepreciation(), 2),
                'elapsed_months'     => $a->elapsedMonths($asOfC),
                'accumulated'        => round($a->accumulatedDepreciation($asOfC), 2),
                'book_value'         => round($a->bookValue($asOfC), 2),
                'account'            => $a->account ? $a->account->code . ' — ' . $a->account->name : null,
                'account_id'         => $a->account_id,
                'notes'              => $a->notes,
            ]);

        return Inertia::render('Books/FixedAssets', [
            'assets'   => $assets,
            'as_of'    => $asOf,
            'accounts' => Account::where('organization_id', $orgId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'totals' => [
                'total_cost'  => $assets->sum('total_cost'),
                'accumulated' => $assets->sum('accumulated'),
                'book_value'  => $assets->sum('book_value'),
            ],
            'can_manage' => auth()->user()->hasRole('super_admin'),
            'depreciation' => [
                'current_period'  => now()->format('Y-m'),
                'monthly_total'   => round($assets->sum('monthly'), 2),
                'posted_periods'  => $this->postedDepreciationPeriods($orgId),
                'has_accounts'    => $this->depreciationAccounts($orgId) !== null,
            ],
        ]);
    }

    /**
     * Auto-jurnal penyusutan bulanan (Tahap 5-6 automasi PDF).
     * Membuat satu jurnal terposting: D 6112 Beban Penyusutan | K 1609
     * Akumulasi Penyusutan sebesar total penyusutan aset yang masih berjalan
     * pada periode terpilih. Guard ref_type/ref_id mencegah posting ganda.
     */
    public function postDepreciation(Request $request)
    {
        $user  = auth()->user();
        abort_unless($user->hasRole('super_admin'), 403);

        $orgId = $user->organization_id;
        $data  = $request->validate(['period' => 'required|date_format:Y-m']);
        $period = $data['period'];

        // Cegah posting ganda untuk periode yang sama.
        $exists = JournalEntry::where('organization_id', $orgId)
            ->where('ref_type', 'depreciation')->where('ref_id', $period)->exists();
        if ($exists) {
            return back()->with('error', "Penyusutan periode $period sudah pernah diposting.");
        }

        $accounts = $this->depreciationAccounts($orgId);
        if (! $accounts) {
            return back()->with('error', 'Akun 6112 (Beban Penyusutan) atau 1609 (Akumulasi Penyusutan) belum ada di COA.');
        }

        $periodStart = \Carbon\Carbon::parse($period . '-01')->startOfMonth();
        $total = 0.0;

        foreach (FixedAsset::where('organization_id', $orgId)->get() as $asset) {
            if (! $asset->purchase_date || (int) $asset->useful_life_months <= 0) {
                continue;
            }
            $start = $asset->purchase_date->copy()->startOfMonth();
            $end   = $start->copy()->addMonths((int) $asset->useful_life_months - 1);
            // Aset menyusut pada periode ini bila periode di rentang [beli, akhir umur].
            if ($periodStart->betweenIncluded($start, $end)) {
                $total += $asset->monthlyDepreciation();
            }
        }

        $total = round($total, 2);
        if ($total <= 0) {
            return back()->with('error', "Tidak ada penyusutan untuk periode $period (tidak ada aset aktif).");
        }

        DB::transaction(function () use ($orgId, $period, $periodStart, $total, $accounts) {
            $entry = JournalEntry::create([
                'organization_id' => $orgId,
                'entry_no'        => $this->generateEntryNo($orgId),
                'entry_date'      => $periodStart->copy()->endOfMonth()->toDateString(),
                'description'     => 'Penyusutan aset tetap periode ' . $periodStart->translatedFormat('F Y'),
                'is_posted'       => true,
                'status'          => 'posted',
                'current_level'   => 0,
                'ref_type'        => 'depreciation',
                'ref_id'          => $period,
                'created_by'      => auth()->id(),
            ]);
            $entry->lines()->create(['account_id' => $accounts['expense'], 'debit' => $total, 'credit' => 0, 'memo' => 'Beban penyusutan bulanan']);
            $entry->lines()->create(['account_id' => $accounts['accum'],   'debit' => 0, 'credit' => $total, 'memo' => 'Akumulasi penyusutan']);
        });

        return back()->with('success', "Jurnal penyusutan periode $period diposting: Rp " . number_format($total, 0, ',', '.') . '.');
    }

    /** Akun penyusutan (6112 beban & 1609 akumulasi); null bila belum lengkap. */
    private function depreciationAccounts(string $orgId): ?array
    {
        $expense = Account::where('organization_id', $orgId)->where('code', '6112')->value('id');
        $accum   = Account::where('organization_id', $orgId)->where('code', '1609')->value('id');

        return ($expense && $accum) ? ['expense' => $expense, 'accum' => $accum] : null;
    }

    /** Daftar periode (YYYY-MM) penyusutan yang sudah diposting. */
    private function postedDepreciationPeriods(string $orgId): array
    {
        return JournalEntry::where('organization_id', $orgId)
            ->where('ref_type', 'depreciation')
            ->orderBy('ref_id', 'desc')
            ->pluck('ref_id')
            ->all();
    }

    private function generateEntryNo(string $orgId): string
    {
        $prefix = 'JE-' . now()->format('Ym') . '-';
        $count  = JournalEntry::where('organization_id', $orgId)
            ->where('entry_no', 'like', $prefix . '%')
            ->count();

        return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        FixedAsset::create([
            ...$this->validated($request),
            'organization_id' => auth()->user()->organization_id,
        ]);

        return back()->with('success', 'Aset berhasil ditambahkan');
    }

    public function update(Request $request, FixedAsset $fixedAsset)
    {
        abort_unless($fixedAsset->organization_id === auth()->user()->organization_id, 403);

        $fixedAsset->update($this->validated($request));

        return back()->with('success', 'Aset berhasil diperbarui');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'description'        => 'required|string|max:255',
            'purchase_date'      => 'required|date',
            'qty'                => 'required|integer|min:1',
            'unit_cost'          => 'required|numeric|min:0',
            'residual_value'     => 'required|numeric|min:0',
            'useful_life_months' => 'required|integer|min:1',
            'account_id'         => 'nullable|exists:accounts,id',
            'notes'              => 'nullable|string',
        ]);
    }

    public function destroy(FixedAsset $fixedAsset)
    {
        abort_unless($fixedAsset->organization_id === auth()->user()->organization_id, 403);

        $fixedAsset->delete();

        return back()->with('success', 'Aset dihapus');
    }
}
