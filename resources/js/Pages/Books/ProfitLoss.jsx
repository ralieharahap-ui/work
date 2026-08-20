import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
const pct = (n) => (n === null || n === undefined ? '—' : `${n}%`);

/** Blok seksi akun (pendapatan / biaya) dengan baris subtotal. */
function Section({ title, subtitle, accounts, total, totalLabel, totalClass, emptyText }) {
    return (
        <div className="mb-4">
            <div className="flex items-baseline justify-between">
                <h2 className="text-white font-semibold">{title}</h2>
                {subtitle && <span className="text-slate-500 text-xs">{subtitle}</span>}
            </div>
            <table className="w-full mt-1">
                <tbody>
                    {accounts.length === 0 && (
                        <tr><td className="table-cell text-slate-500 text-sm italic">{emptyText}</td></tr>
                    )}
                    {accounts.map((a) => (
                        <tr key={a.code} className="border-b border-slate-700/40">
                            <td className="table-cell font-mono text-xs w-24">{a.code}</td>
                            <td className="table-cell">{a.name}</td>
                            <td className="table-cell text-right">{fmt(a.amount)}</td>
                        </tr>
                    ))}
                    <tr className="border-t border-slate-600">
                        <td colSpan={2} className="table-cell font-medium">{totalLabel}</td>
                        <td className={`table-cell text-right font-bold ${totalClass}`}>{fmt(total)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

/** Baris subtotal berjenjang (Laba Kotor / Laba Operasi). */
function Subtotal({ label, value, sub }) {
    const positive = value >= 0;
    return (
        <div className="flex items-center justify-between px-4 py-2.5 my-3 rounded-lg bg-slate-800/60 border border-slate-700">
            <div>
                <span className="font-semibold text-white">{label}</span>
                {sub && <span className="text-slate-500 text-xs ml-2">{sub}</span>}
            </div>
            <span className={`font-bold ${positive ? 'text-sky-300' : 'text-red-400'}`}>{fmt(value)}</span>
        </div>
    );
}

export default function ProfitLoss({
    revenue, direct_cost, fixed_cost, gross_profit, gross_margin_pct, net, period_from, period_to,
}) {
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
                    {/* Pendapatan */}
                    <Section
                        title="Pendapatan"
                        accounts={revenue.accounts}
                        total={revenue.total}
                        totalLabel="Total Pendapatan"
                        totalClass="text-emerald-400"
                        emptyText="Belum ada pendapatan pada periode ini."
                    />

                    {/* Biaya Langsung (Direct Cost) */}
                    <Section
                        title="Biaya Langsung (Direct Cost)"
                        subtitle="Biaya terkait proyek — kode 5xxx"
                        accounts={direct_cost.accounts}
                        total={direct_cost.total}
                        totalLabel="Total Biaya Langsung"
                        totalClass="text-amber-400"
                        emptyText="Belum ada biaya langsung pada periode ini."
                    />

                    {/* Laba Kotor */}
                    <Subtotal label="Laba Kotor" value={gross_profit} sub={`Margin kotor ${pct(gross_margin_pct)}`} />

                    {/* Biaya Tetap (Fixed Cost / OPEX) */}
                    <Section
                        title="Biaya Tetap (Fixed Cost / OPEX)"
                        subtitle="Beban operasional & umum — kode 6xxx"
                        accounts={fixed_cost.accounts}
                        total={fixed_cost.total}
                        totalLabel="Total Biaya Tetap"
                        totalClass="text-red-400"
                        emptyText="Belum ada biaya tetap pada periode ini."
                    />

                    {/* Laba Bersih */}
                    <div className={`flex items-center justify-between px-4 py-3 rounded-lg ${net >= 0 ? 'bg-emerald-900/30' : 'bg-red-900/30'}`}>
                        <span className="font-semibold text-white">{net >= 0 ? 'Laba Bersih' : 'Rugi Bersih'}</span>
                        <span className={`font-bold text-lg ${net >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>{fmt(net)}</span>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
