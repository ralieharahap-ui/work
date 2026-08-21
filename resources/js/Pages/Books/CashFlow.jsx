import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
const tgl = (s) => new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });

function Activity({ title, rows, total, accent }) {
    return (
        <div className="mb-4">
            <div className="flex items-baseline justify-between mb-1">
                <h3 className="text-white font-medium text-sm">{title}</h3>
            </div>
            <table className="w-full">
                <tbody>
                    {rows.length === 0 && (
                        <tr><td className="px-3 py-1.5 text-slate-500 text-sm italic">— tidak ada arus kas —</td></tr>
                    )}
                    {rows.map((r, i) => (
                        <tr key={i} className="border-b border-slate-700/40">
                            <td className="px-3 py-1.5 text-sm text-slate-200">
                                <span className="text-slate-500 text-xs mr-2">{tgl(r.date)}</span>
                                {r.counterpart || r.description}
                                <span className="font-mono text-[10px] text-slate-500 ml-2">{r.entry_no}</span>
                            </td>
                            <td className={`px-3 py-1.5 text-sm text-right tabular-nums ${r.amount >= 0 ? 'text-slate-200' : 'text-red-300'}`}>{fmt(r.amount)}</td>
                        </tr>
                    ))}
                    <tr className="border-t border-slate-600/70">
                        <td className="px-3 py-1.5 text-sm text-slate-300">Arus Kas Bersih {title}</td>
                        <td className={`px-3 py-1.5 text-sm font-bold text-right tabular-nums ${accent}`}>{fmt(total)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

export default function CashFlow({ period_from, period_to, sections, totals, net_change, cash_opening, cash_closing, is_reconciled }) {
    const [from, setFrom] = useState(period_from);
    const [to, setTo] = useState(period_to);
    const reload = () => router.get(route('books.cash-flow'), { from, to }, { preserveState: true });

    return (
        <>
            <Head title="Arus Kas" />
            <AppLayout title="Laporan Arus Kas (PSAK 2)">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div><label className="label">Dari</label>
                        <input type="date" className="input w-auto" value={from} onChange={(e) => setFrom(e.target.value)} onBlur={reload} /></div>
                    <div><label className="label">Sampai</label>
                        <input type="date" className="input w-auto" value={to} onChange={(e) => setTo(e.target.value)} onBlur={reload} /></div>
                    {is_reconciled
                        ? <span className="badge badge-green mb-2">✓ Terekonsiliasi</span>
                        : <span className="badge badge-amber mb-2">⚠ Selisih</span>}
                    <div className="flex-1" />
                    <span className="text-xs text-slate-500 mb-2 hidden sm:inline">Metode langsung</span>
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="card max-w-2xl">
                    <Activity title="Aktivitas Operasi" rows={sections.operasi} total={totals.operasi} accent="text-sky-300" />
                    <Activity title="Aktivitas Investasi" rows={sections.investasi} total={totals.investasi} accent="text-purple-300" />
                    <Activity title="Aktivitas Pendanaan" rows={sections.pendanaan} total={totals.pendanaan} accent="text-amber-300" />

                    <div className="mt-2 space-y-1">
                        <div className="flex items-center justify-between px-4 py-2.5 rounded-lg bg-slate-800/70 border border-slate-700">
                            <span className="font-semibold text-white">Kenaikan / (Penurunan) Kas Bersih</span>
                            <span className={`font-bold tabular-nums ${net_change >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>{fmt(net_change)}</span>
                        </div>
                        <div className="flex items-center justify-between px-4 py-1.5 text-sm">
                            <span className="text-slate-400">Saldo Kas Awal</span>
                            <span className="tabular-nums text-slate-200">{fmt(cash_opening)}</span>
                        </div>
                        <div className="flex items-center justify-between px-4 py-2.5 rounded-lg bg-emerald-900/30">
                            <span className="font-semibold text-white">Saldo Kas Akhir</span>
                            <span className="font-bold tabular-nums text-emerald-400">{fmt(cash_closing)}</span>
                        </div>
                    </div>
                    <p className="text-slate-500 text-xs mt-3">Kas &amp; Bank (akun kontrol "Kas"/"Kas di Bank"). Klasifikasi berdasarkan akun lawan tiap jurnal (Investasi = aset tetap/takberwujud; Pendanaan = ekuitas/pihak berelasi; selain itu Operasi).</p>
                </div>
            </AppLayout>
        </>
    );
}
