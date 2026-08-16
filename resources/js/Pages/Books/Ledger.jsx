import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.abs(Number(n) || 0));
const signed = (n) => `${Number(n) < 0 ? '(' : ''}${fmt(n)}${Number(n) < 0 ? ')' : ''}`;
const tgl = (s) => new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

export default function Ledger({
    accounts, account_id, account, year,
    opening_balance, rows, total_debit, total_credit, ending_balance,
}) {
    const [acc, setAcc] = useState(account_id ?? '');
    const [yr, setYr] = useState(year);

    const reload = (next = {}) =>
        router.get(route('books.ledger.index'), { account_id: acc, year: yr, ...next }, { preserveState: true });

    return (
        <>
            <Head title="Buku Besar" />
            <AppLayout title="Buku Besar (General Ledger)">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div className="min-w-[240px]">
                        <label className="label">Kode Akun</label>
                        <select className="input" value={acc}
                            onChange={(e) => { setAcc(e.target.value); reload({ account_id: e.target.value }); }}>
                            {accounts.map((a) => (
                                <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="label">Periode (Tahun)</label>
                        <input type="number" className="input w-28" value={yr}
                            onChange={(e) => setYr(e.target.value)}
                            onBlur={() => reload()} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                {account && (
                    <div className="card overflow-x-auto">
                        <div className="mb-3">
                            <h2 className="text-white font-semibold">{account.code} — {account.name}</h2>
                            <p className="text-slate-400 text-sm">Periode tahun {year}</p>
                        </div>
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-slate-700">
                                    <th className="table-header">Tanggal</th>
                                    <th className="table-header">No. Jurnal</th>
                                    <th className="table-header">Keterangan</th>
                                    <th className="table-header text-right">Debet</th>
                                    <th className="table-header text-right">Kredit</th>
                                    <th className="table-header text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="border-b border-slate-700/50 bg-slate-900/40">
                                    <td className="table-cell" colSpan={3}>Saldo Awal</td>
                                    <td className="table-cell text-right">—</td>
                                    <td className="table-cell text-right">—</td>
                                    <td className="table-cell text-right font-medium">{signed(opening_balance)}</td>
                                </tr>
                                {rows.map((r, i) => (
                                    <tr key={i} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                        <td className="table-cell whitespace-nowrap">{tgl(r.entry_date)}</td>
                                        <td className="table-cell font-mono text-xs">{r.entry_no}</td>
                                        <td className="table-cell">{r.description}</td>
                                        <td className="table-cell text-right">{r.debit ? fmt(r.debit) : '—'}</td>
                                        <td className="table-cell text-right">{r.credit ? fmt(r.credit) : '—'}</td>
                                        <td className="table-cell text-right">{signed(r.balance)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t border-slate-600">
                                    <td colSpan={3} className="table-cell font-medium">Total Mutasi / Saldo Akhir</td>
                                    <td className="table-cell text-right font-bold">{fmt(total_debit)}</td>
                                    <td className="table-cell text-right font-bold">{fmt(total_credit)}</td>
                                    <td className="table-cell text-right font-bold text-blue-300">{signed(ending_balance)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
