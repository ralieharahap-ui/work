import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

function Section({ title, rows, total, totalLabel, accent }) {
    return (
        <table className="w-full mb-4">
            <thead>
                <tr className="border-b border-slate-700">
                    <th className="table-header" colSpan={2}>{title}</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((a) => (
                    <tr key={a.code} className="border-b border-slate-700/40">
                        <td className="table-cell">
                            <span className="font-mono text-xs text-slate-500 mr-2">{a.code}</span>{a.name}
                        </td>
                        <td className="table-cell text-right">{fmt(a.amount)}</td>
                    </tr>
                ))}
                <tr className="border-t border-slate-600">
                    <td className="table-cell font-medium">{totalLabel}</td>
                    <td className={`table-cell text-right font-bold ${accent}`}>{fmt(total)}</td>
                </tr>
            </tbody>
        </table>
    );
}

export default function BalanceSheet({
    assets, liabilities, equity, net_income,
    total_assets, total_liab, total_equity, total_liab_equity, is_balanced, as_of,
}) {
    const [date, setDate] = useState(as_of);

    return (
        <>
            <Head title="Neraca" />
            <AppLayout title="Neraca (Balance Sheet)">
                <div className="flex flex-wrap items-center gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Per Tanggal</label>
                        <input type="date" className="input w-auto" value={date}
                            onChange={(e) => setDate(e.target.value)}
                            onBlur={() => router.get(route('books.balance-sheet'), { as_of: date }, { preserveState: true })} />
                    </div>
                    {is_balanced
                        ? <span className="badge badge-green mt-5">✓ Seimbang</span>
                        : <span className="badge badge-red mt-5">✗ Tidak Seimbang</span>}
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="grid lg:grid-cols-2 gap-5">
                    <div className="card">
                        <h2 className="text-white font-semibold mb-3">AKTIVA</h2>
                        <Section title="Aset" rows={assets} total={total_assets}
                            totalLabel="TOTAL AKTIVA" accent="text-blue-300" />
                    </div>

                    <div className="card">
                        <h2 className="text-white font-semibold mb-3">KEWAJIBAN & MODAL</h2>
                        <Section title="Kewajiban" rows={liabilities} total={total_liab}
                            totalLabel="Total Kewajiban" accent="text-amber-300" />
                        <Section
                            title="Modal"
                            rows={[...equity, { code: '—', name: net_income >= 0 ? 'Laba Tahun Berjalan' : 'Rugi Tahun Berjalan', amount: net_income }]}
                            total={total_equity}
                            totalLabel="Total Modal" accent="text-emerald-300" />
                        <div className={`flex items-center justify-between px-4 py-3 rounded-lg ${is_balanced ? 'bg-emerald-900/30' : 'bg-red-900/30'}`}>
                            <span className="font-semibold text-white">TOTAL KEWAJIBAN & MODAL</span>
                            <span className={`font-bold ${is_balanced ? 'text-emerald-400' : 'text-red-400'}`}>{fmt(total_liab_equity)}</span>
                        </div>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
