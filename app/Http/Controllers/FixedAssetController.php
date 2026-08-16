<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
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
        ]);
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
