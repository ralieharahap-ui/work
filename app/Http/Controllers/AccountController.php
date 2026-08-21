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
                    'id'             => $account->id,
                    'code'           => $account->code,
                    'name'           => $account->name,
                    'type'           => $account->type,
                    'account_type'   => $account->account_type,
                    'fs_group'       => $account->fs_group,
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
            'fs_group'       => 'nullable|string|max:100',
            'normal_balance' => 'nullable|in:Db,Kr',
            'report'         => 'nullable|in:NRC,LR',
            'is_active'      => 'boolean',
        ]);
    }

    /**
     * Referensi Control Account (akun kontrol) — selaras PSAK 1 (Laporan Posisi
     * Keuangan) & istilah SAK Indonesia. Kolom:
     * [Kelompok FS, TYPE AKUN, Posisi Normal (Db/Kr), Laporan (NRC=Neraca / LR=Laba Rugi)].
     */
    private function controlAccounts(): array
    {
        return [
            // Aset Lancar
            ['Aset Lancar',              'Kas',                                'Db', 'NRC'],
            ['Aset Lancar',              'Kas di Bank',                        'Db', 'NRC'],
            ['Aset Lancar',              'Piutang Usaha',                      'Db', 'NRC'],
            ['Aset Lancar',              'Cadangan Kerugian Penurunan Nilai',  'Kr', 'NRC'],
            ['Aset Lancar',              'Persediaan',                         'Db', 'NRC'],
            ['Aset Lancar',              'Uang Muka',                          'Db', 'NRC'],
            ['Aset Lancar',              'Beban Dibayar Dimuka',               'Db', 'NRC'],
            ['Aset Lancar',              'Pajak Dibayar Dimuka',               'Db', 'NRC'],
            // Aset Tidak Lancar
            ['Aset Tidak Lancar',        'Aset Tetap',                         'Db', 'NRC'],
            ['Aset Tidak Lancar',        'Akumulasi Penyusutan',               'Kr', 'NRC'],
            ['Aset Tidak Lancar',        'Aset Takberwujud',                   'Db', 'NRC'],
            // Liabilitas Jangka Pendek
            ['Liabilitas Jangka Pendek', 'Utang Usaha',                        'Kr', 'NRC'],
            ['Liabilitas Jangka Pendek', 'Utang Pajak',                        'Kr', 'NRC'],
            ['Liabilitas Jangka Pendek', 'Beban Masih Harus Dibayar',          'Kr', 'NRC'],
            // Liabilitas Jangka Panjang
            ['Liabilitas Jangka Panjang','Utang Pihak Berelasi',               'Kr', 'NRC'],
            // Ekuitas
            ['Ekuitas',                  'Ekuitas',                            'Kr', 'NRC'],
            // Laba Rugi
            ['Pendapatan',               'Pendapatan Usaha',                   'Kr', 'LR'],
            ['Pendapatan Lain-lain',     'Pendapatan Lain-lain',               'Kr', 'LR'],
            ['Beban Pokok Penjualan',    'Beban Pokok Penjualan',              'Db', 'LR'],
            ['Beban Usaha',              'Beban Usaha',                        'Db', 'LR'],
            ['Beban Lain-lain',          'Beban Lain-lain',                    'Db', 'LR'],
        ];
    }
}
