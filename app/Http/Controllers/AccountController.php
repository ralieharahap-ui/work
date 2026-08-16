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
                $net    = $debit - $credit;

                // Tempatkan saldo neto pada kolom sesuai posisi normal akun.
                return [
                    'code'           => $account->code,
                    'name'           => $account->name,
                    'type'           => $account->type,
                    'account_type'   => $account->account_type,
                    'normal_balance' => $account->normal_balance,
                    'report'         => $account->report,
                    'debit'          => $net > 0 ? $net : 0,
                    'credit'         => $net < 0 ? -$net : 0,
                ];
            });

        return Inertia::render('Books/ChartOfAccounts', [
            'accounts'      => $accounts,
            'total_debit'   => $accounts->sum('debit'),
            'total_credit'  => $accounts->sum('credit'),
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
            'normal_balance' => 'nullable|in:Db,Kr',
            'report'         => 'nullable|in:NRC,LR',
            'is_active'      => 'boolean',
        ]);
    }

    /**
     * Referensi Control Account — poin 1.7.
     * 21 TYPE AKUN dengan posisi normal (Db/Kr) dan pemetaan laporan (NRC / LR).
     */
    private function controlAccounts(): array
    {
        // [Kelompok, TYPE AKUN, Posisi Normal (Db/Kr), Laporan (NRC=Neraca / LR=Laba Rugi)]
        return [
            ['AKTIVA',     'Kas',                                  'Db', 'NRC'],
            ['AKTIVA',     'Kas di Bank',                          'Db', 'NRC'],
            ['AKTIVA',     'Piutang Usaha',                        'Db', 'NRC'],
            ['AKTIVA',     'Aset Lancar Lainnya',                  'Db', 'NRC'],
            ['AKTIVA',     'Persediaan',                           'Db', 'NRC'],
            ['AKTIVA',     'PPN Masukan',                          'Db', 'NRC'],
            ['AKTIVA',     'Aset Tetap',                           'Db', 'NRC'],
            ['AKTIVA',     'Aset Lain-lain',                       'Db', 'NRC'],
            ['AKTIVA',     'Aset Tetap (Kontra/Akum. Penyusutan)', 'Db', 'NRC'],
            ['KEWAJIBAN',  'Utang Usaha',                          'Kr', 'NRC'],
            ['KEWAJIBAN',  'Liabilitas Jangka Pendek Lainnya',     'Kr', 'NRC'],
            ['KEWAJIBAN',  'Liabilitas Jangka Panjang Lainnya',    'Kr', 'NRC'],
            ['KEWAJIBAN',  'Kewajiban Pajak',                      'Kr', 'NRC'],
            ['KEWAJIBAN',  'Liabilitas Lain-lain',                 'Kr', 'NRC'],
            ['MODAL',      'Equity',                               'Kr', 'NRC'],
            ['PENDAPATAN', 'Pendapatan Usaha',                     'Kr', 'LR'],
            ['PENDAPATAN', 'Pendapatan Lain-lain',                 'Kr', 'LR'],
            ['HPP',        'Harga Pokok Penjualan',                'Db', 'LR'],
            ['BEBAN',      'Beban Usaha',                          'Db', 'LR'],
            ['BEBAN',      'Beban Non-operasional',                'Db', 'LR'],
            ['BEBAN',      'Expences-Perorangan',                  'Db', 'LR'],
        ];
    }
}
