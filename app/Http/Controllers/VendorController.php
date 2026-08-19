<?php

namespace App\Http\Controllers;

use App\Models\JournalLine;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(): Response
    {
        $orgId = auth()->user()->organization_id;

        $vendors  = Vendor::where('organization_id', $orgId)->orderBy('code')->get();
        $movements = $this->movementsByCode($orgId); // [aux_code => net (kredit - debit) pada akun liability]

        $rows = $vendors->map(function ($v) use ($movements) {
            $opening = (float) $v->payable_balance;
            $mv      = (float) ($movements[$v->code] ?? 0);
            return [
                'id'              => $v->id,
                'code'            => $v->code,
                'name'            => $v->name,
                'phone'           => $v->phone,
                'address'         => $v->address,
                'is_active'       => $v->is_active,
                'opening_balance' => $opening,
                'movement'        => $mv,
                'current_balance' => $opening + $mv,
            ];
        });

        return Inertia::render('Books/Vendors', [
            'vendors'    => $rows,
            'can_manage' => auth()->user()->hasRole('super_admin'),
        ]);
    }

    /**
     * Riwayat utang vendor (subledger) — dari jurnal Posted yang baris utang-nya
     * diberi kode bantu = kode vendor. Saldo berjalan otomatis.
     */
    public function show(Vendor $vendor): Response
    {
        abort_unless($vendor->organization_id === auth()->user()->organization_id, 403);

        $lines = JournalLine::where('aux_code', $vendor->code)
            ->whereHas('account', fn ($q) => $q->where('type', 'liability'))
            ->whereHas('journalEntry', fn ($q) => $q->where('organization_id', $vendor->organization_id)
                ->where('is_posted', true))
            ->with(['journalEntry:id,entry_no,entry_date,description', 'account:id,code,name'])
            ->get()
            ->sortBy(fn ($l) => [$l->journalEntry->entry_date->toDateString(), $l->journalEntry->entry_no])
            ->values();

        $running = (float) $vendor->payable_balance;
        $rows = $lines->map(function ($l) use (&$running) {
            $debit  = (float) $l->debit;
            $credit = (float) $l->credit;
            $running += $credit - $debit; // utang bertambah bila kredit, berkurang bila debit
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

        return Inertia::render('Books/VendorLedger', [
            'vendor'          => $vendor->only(['id', 'code', 'name', 'phone', 'address']),
            'opening_balance' => (float) $vendor->payable_balance,
            'rows'            => $rows,
            'current_balance' => $running,
            'total_debit'     => $rows->sum('debit'),
            'total_credit'    => $rows->sum('credit'),
        ]);
    }

    public function store(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        Vendor::create([...$this->validated($request, $orgId), 'organization_id' => $orgId]);

        return back()->with('success', 'Vendor berhasil ditambahkan');
    }

    public function update(Request $request, Vendor $vendor)
    {
        abort_unless($vendor->organization_id === auth()->user()->organization_id, 403);

        $vendor->update($this->validated($request, $vendor->organization_id, $vendor->id));

        return back()->with('success', 'Vendor berhasil diperbarui');
    }

    public function destroy(Vendor $vendor)
    {
        abort_unless($vendor->organization_id === auth()->user()->organization_id, 403);

        $vendor->delete();

        return back()->with('success', 'Vendor dihapus');
    }

    /** Total pergerakan utang (kredit - debit) per kode bantu, dari jurnal Posted (akun liability). */
    private function movementsByCode(string $orgId): array
    {
        return JournalLine::selectRaw('aux_code, SUM(credit - debit) as mv')
            ->whereNotNull('aux_code')
            ->whereHas('account', fn ($q) => $q->where('type', 'liability'))
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
                Rule::unique('vendors', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))
                    ->ignore($ignoreId),
            ],
            'name'            => 'required|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:255',
            'payable_balance' => 'nullable|numeric',
            'is_active'       => 'boolean',
        ]);
    }
}
