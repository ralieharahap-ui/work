import { Head, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
const pct = (n) => (n === null || n === undefined ? '—' : `${n}%`);

function Tile({ label, value, sub, accent = 'text-white' }) {
    return (
        <div className="kpi-card">
            <p className="kpi-label">{label}</p>
            <p className={`kpi-value ${accent}`}>{fmt(value)}</p>
            {sub && <p className="text-slate-500 text-[11px] mt-1">{sub}</p>}
        </div>
    );
}

export default function Dashboard({ year, pnl, segments, position }) {
    const [yr, setYr] = useState(year);

    return (
        <>
            <Head title="Dashboard Akuntansi" />
            <AppLayout title="Dashboard Manajemen — Akuntansi">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Tahun</label>
                        <input type="number" className="input w-28" value={yr}
                            onChange={(e) => setYr(e.target.value)}
                            onBlur={() => router.get(route('books.dashboard'), { year: yr }, { preserveState: true })} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                {/* KPI utama */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-5">
                    <Tile label={`Pendapatan Usaha ${year}`} value={pnl.revenue} accent="text-sky-300" sub={`+ Lain-lain ${fmt(pnl.other_income)}`} />
                    <Tile label="HPP" value={pnl.cogs} accent="text-amber-300" />
                    <Tile label="Laba Kotor" value={pnl.gross_profit} accent="text-emerald-300" sub={`Margin kotor ${pct(pnl.gross_margin_pct)}`} />
                    <Tile label="Laba Bersih" value={pnl.net_income} accent={pnl.net_income >= 0 ? 'text-emerald-300' : 'text-red-400'} sub={`OPEX ${fmt(pnl.opex)}`} />
                </div>

                {/* Ringkasan P&L + posisi neraca */}
                <div className="grid gap-5 lg:grid-cols-2 mb-5">
                    <div className="card">
                        <h2 className="text-white font-semibold mb-3">Ringkasan Laba/Rugi {year}</h2>
                        <dl className="space-y-1.5 text-sm">
                            <Row k="Pendapatan Usaha" v={pnl.revenue} />
                            <Row k="Harga Pokok Penjualan" v={-pnl.cogs} />
                            <Row k="Laba Kotor" v={pnl.gross_profit} bold />
                            <Row k="Pendapatan Lain-lain" v={pnl.other_income} />
                            <Row k="Beban Operasional (OPEX)" v={-pnl.opex} />
                            <Row k="Beban Lain-lain" v={-pnl.other_expense} />
                            <div className="border-t border-slate-700 my-1" />
                            <Row k="Laba Bersih" v={pnl.net_income} bold accent={pnl.net_income >= 0 ? 'text-emerald-300' : 'text-red-400'} />
                        </dl>
                        <div className="mt-3 flex gap-3 text-xs print:hidden">
                            <Link href={route('books.profit-loss')} className="text-blue-400 hover:text-blue-300">Detail Laba/Rugi →</Link>
                            <Link href={route('books.balance-sheet')} className="text-blue-400 hover:text-blue-300">Neraca →</Link>
                        </div>
                    </div>

                    <div className="card">
                        <h2 className="text-white font-semibold mb-3">Posisi Ringkas (s/d akhir {year})</h2>
                        <div className="grid grid-cols-3 gap-3">
                            <Tile label="Kas & Bank" value={position.cash} accent="text-sky-300" />
                            <Tile label="Piutang Usaha" value={position.receivable} accent="text-blue-300" />
                            <Tile label="Utang Usaha" value={position.payable} accent="text-amber-300" />
                        </div>
                        <p className="text-slate-500 text-xs mt-3">Kas dari akun 1101-1103 · Piutang 1201-1203 · Utang 2101-2102.</p>
                    </div>
                </div>

                {/* Margin per segmen */}
                <div className="card overflow-x-auto">
                    <h2 className="text-white font-semibold mb-3">Margin per Segmen Pendapatan</h2>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Segmen</th>
                                <th className="table-header text-right">Pendapatan</th>
                                <th className="table-header text-right">Biaya Langsung</th>
                                <th className="table-header text-right">Margin</th>
                                <th className="table-header text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            {segments.map((s) => (
                                <tr key={s.name} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-medium">{s.name}</td>
                                    <td className="table-cell text-right">{fmt(s.revenue)}</td>
                                    <td className="table-cell text-right">{fmt(s.cogs)}</td>
                                    <td className={`table-cell text-right font-bold ${s.margin >= 0 ? 'text-emerald-300' : 'text-red-400'}`}>{fmt(s.margin)}</td>
                                    <td className="table-cell text-right">{pct(s.margin_pct)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}

function Row({ k, v, bold, accent }) {
    return (
        <div className={`flex justify-between ${bold ? 'font-semibold' : ''}`}>
            <dt className="text-slate-300">{k}</dt>
            <dd className={accent || (bold ? 'text-white' : 'text-slate-200')}>{fmt(v)}</dd>
        </div>
    );
}
