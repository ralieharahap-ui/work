<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Daftar Akun (Chart of Accounts) — poin 1.6.
     * Kolom: KODE Akun | NAMA AKUN | TYPE Akun | DEBET | KREDIT.
     * Saldo DEBET/KREDIT dihitung dari jurnal yang telah dirilis (is_posted = true).
     */
    public function index(): Response
    {
        $orgId = auth()->user()->organization_id;

        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->with(['lines' => fn ($q) => $q->whereHas('journalEntry',
                fn ($q) => $q->where('is_posted', true)
            )])
            ->orderBy('code')
            ->get()
            ->map(function ($account) {
                $debit  = (float) $account->lines->sum('debit');
                $credit = (float) $account->lines->sum('credit');

                return [
                    'id'             => $account->id,
                    'code'           => $account->code,
                    'legacy_code'    => $account->legacy_code,
                    'name'           => $account->name,
                    'type'           => $account->type,
                    'account_type'   => $account->account_type,
                    'fs_group'       => $account->fs_group,
                    'normal_balance' => $account->normal_balance,
                    'report'         => $account->report,
                    'is_header'      => (bool) $account->is_header,
                    'net'            => $debit - $credit,
                ];
            });

        // Akun header menampilkan subtotal akun detail di bawahnya (awalan kode tanpa nol di belakang).
        $details = $accounts->where('is_header', false);
        $accounts = $accounts->map(function ($a) use ($details) {
            if ($a['is_header']) {
                $prefix = rtrim($a['code'], '0') ?: $a['code'];
                $a['net'] = $details->filter(fn ($d) => str_starts_with($d['code'], $prefix))->sum('net');
            }
            // Tempatkan saldo neto pada kolom sesuai posisi normal akun.
            $a['debit']  = $a['net'] > 0 ? $a['net'] : 0;
            $a['credit'] = $a['net'] < 0 ? -$a['net'] : 0;
            return $a;
        })->values();
        $details = $accounts->where('is_header', false);

        return Inertia::render('Books/ChartOfAccounts', [
            'accounts'      => $accounts,
            'total_debit'   => $details->sum('debit'),
            'total_credit'  => $details->sum('credit'),
            'control_accounts' => $this->controlAccounts(),
            'can_manage'    => auth()->user()->hasRole('super_admin'),
            'type_options'  => ['asset', 'liability', 'equity', 'revenue', 'expense'],
        ]);
    }

    public function store(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        $validated = $this->validated($request, $orgId);

        Account::create([...$validated, 'organization_id' => $orgId]);

        return back()->with('success', 'Akun berhasil ditambahkan');
    }

    public function update(Request $request, Account $account)
    {
        abort_unless($account->organization_id === auth()->user()->organization_id, 403);

        $account->update($this->validated($request, $account->organization_id, $account->id));

        return back()->with('success', 'Akun berhasil diperbarui');
    }

    public function destroy(Account $account)
    {
        abort_unless($account->organization_id === auth()->user()->organization_id, 403);

        // Cegah hapus akun yang sudah dipakai di jurnal.
        if ($account->lines()->exists()) {
            return back()->with('error', 'Akun tidak dapat dihapus karena sudah digunakan pada jurnal.');
        }

        $account->delete();

        return back()->with('success', 'Akun dihapus');
    }

    private function validated(Request $request, string $orgId, ?string $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('accounts', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))
                    ->ignore($ignoreId),
            ],
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:asset,liability,equity,revenue,expense',
            'account_type'   => 'nullable|string|max:100',
            'fs_group'       => 'nullable|string|max:100',
            'normal_balance' => 'nullable|in:Db,Kr',
            'report'         => 'nullable|in:NRC,LR',
            'is_active'      => 'boolean',
            'is_header'      => 'boolean',
        ]);
    }

    /**
     * Referensi Kelompok Akun (Control Account) — diturunkan dari COA revisi PSAK 2026:
     * [Kelompok laporan (sub laporan), Kelompok akun, Posisi normal, Laporan].
     */
    private function controlAccounts(): array
    {
        $rows = require database_path('data/coa_psak_2026.php');

        return collect($rows)
            ->reject(fn ($r) => $r[7])
            ->map(fn ($r) => [$r[6], $r[2], $r[4], $r[5]])
            ->unique(fn ($r) => $r[1] . '|' . $r[2])
            ->values()
            ->all();
    }
}
