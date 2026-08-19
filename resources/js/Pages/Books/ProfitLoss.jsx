import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

export default function ProfitLoss({ revenue, expense, net, period_from, period_to }) {
    const [from, setFrom] = useState(period_from);
    const [to, setTo] = useState(period_to);

    const reload = () => router.get(route('books.profit-loss'), { from, to }, { preserveState: true });

    return (
        <>
            <Head title="Laba/Rugi" />
            <AppLayout title="Laporan Laba/Rugi">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Dari</label>
                        <input type="date" className="input w-auto" value={from}
                            onChange={(e) => setFrom(e.target.value)} onBlur={reload} />
                    </div>
                    <div>
                        <label className="label">Sampai</label>
                        <input type="date" className="input w-auto" value={to}
                            onChange={(e) => setTo(e.target.value)} onBlur={reload} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="card max-w-2xl">
                    <h2 className="text-white font-semibold mb-1">Pendapatan</h2>
                    <table className="w-full mb-4">
                        <tbody>
                            {revenue.accounts.map((a) => (
                                <tr key={a.code} className="border-b border-slate-700/40">
                                    <td className="table-cell font-mono text-xs w-24">{a.code}</td>
                                    <td className="table-cell">{a.name}</td>
                                    <td className="table-cell text-right">{fmt(a.amount)}</td>
                                </tr>
                            ))}
                            <tr className="border-t border-slate-600">
                                <td colSpan={2} className="table-cell font-medium">Total Pendapatan</td>
                                <td className="table-cell text-right font-bold text-emerald-400">{fmt(revenue.total)}</td>
                            </tr>
                        </tbody>
                    </table>

                    <h2 className="text-white font-semibold mb-1">Beban</h2>
                    <table className="w-full mb-4">
                        <tbody>
                            {expense.accounts.map((a) => (
                                <tr key={a.code} className="border-b border-slate-700/40">
                                    <td className="table-cell font-mono text-xs w-24">{a.code}</td>
                                    <td className="table-cell">{a.name}</td>
                                    <td className="table-cell text-right">{fmt(a.amount)}</td>
                                </tr>
                            ))}
                            <tr className="border-t border-slate-600">
                                <td colSpan={2} className="table-cell font-medium">Total Beban</td>
                                <td className="table-cell text-right font-bold text-red-400">{fmt(expense.total)}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div className={`flex items-center justify-between px-4 py-3 rounded-lg ${net >= 0 ? 'bg-emerald-900/30' : 'bg-red-900/30'}`}>
                        <span className="font-semibold text-white">{net >= 0 ? 'Laba Bersih' : 'Rugi Bersih'}</span>
                        <span className={`font-bold text-lg ${net >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>{fmt(net)}</span>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
