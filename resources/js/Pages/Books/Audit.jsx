import { Head, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
const tgl = (s) => new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const SEV = {
    high:   { label: 'Tinggi', cls: 'badge-red',   dot: 'bg-red-500' },
    medium: { label: 'Sedang', cls: 'badge-amber', dot: 'bg-amber-500' },
    low:    { label: 'Rendah', cls: 'badge-slate', dot: 'bg-slate-500' },
};

export default function Audit({ year, exceptions, summary, margin }) {
    const [yr, setYr] = useState(year);
    const [filter, setFilter] = useState('all');

    const shown = filter === 'all' ? exceptions : exceptions.filter((e) => e.severity === filter);

    return (
        <>
            <Head title="Audit AI" />
            <AppLayout title="Layer Audit AI — Antrean Pengecualian">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Tahun</label>
                        <input type="number" className="input w-28" value={yr}
                            onChange={(e) => setYr(e.target.value)}
                            onBlur={() => router.get(route('books.audit'), { year: yr }, { preserveState: true })} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                {/* Ringkasan */}
                <div className="grid gap-3 sm:grid-cols-4 mb-5">
                    <button onClick={() => setFilter('all')} className={`kpi-card text-left ${filter === 'all' ? 'ring-2 ring-blue-500' : ''}`}>
                        <p className="kpi-label">Total Pengecualian</p>
                        <p className="kpi-value">{summary.total}</p>
                    </button>
                    <button onClick={() => setFilter('high')} className={`kpi-card text-left ${filter === 'high' ? 'ring-2 ring-red-500' : ''}`}>
                        <p className="kpi-label">Tinggi</p>
                        <p className="kpi-value text-red-400">{summary.high}</p>
                    </button>
                    <button onClick={() => setFilter('medium')} className={`kpi-card text-left ${filter === 'medium' ? 'ring-2 ring-amber-500' : ''}`}>
                        <p className="kpi-label">Sedang</p>
                        <p className="kpi-value text-amber-400">{summary.medium}</p>
                    </button>
                    <button onClick={() => setFilter('low')} className={`kpi-card text-left ${filter === 'low' ? 'ring-2 ring-slate-500' : ''}`}>
                        <p className="kpi-label">Rendah</p>
                        <p className="kpi-value text-slate-300">{summary.low}</p>
                    </button>
                </div>

                {/* Margin negatif per bulan */}
                {margin.length > 0 && (
                    <div className="card mb-5 border border-red-500/30 bg-red-500/5">
                        <h2 className="text-red-300 font-semibold mb-2">⚠️ Margin Negatif Terdeteksi</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="table-header">Bulan</th>
                                        <th className="table-header text-right">Pendapatan</th>
                                        <th className="table-header text-right">HPP</th>
                                        <th className="table-header text-right">Margin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {margin.map((m) => (
                                        <tr key={m.month} className="border-b border-slate-700/50">
                                            <td className="table-cell">{m.month}</td>
                                            <td className="table-cell text-right">{fmt(m.revenue)}</td>
                                            <td className="table-cell text-right">{fmt(m.cogs)}</td>
                                            <td className="table-cell text-right font-bold text-red-400">{fmt(m.margin)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Antrean pengecualian */}
                {shown.length === 0 ? (
                    <div className="card text-center py-10">
                        <p className="text-4xl mb-2">✅</p>
                        <p className="text-emerald-300 font-medium">Tidak ada pengecualian pada filter ini.</p>
                        <p className="text-slate-500 text-sm mt-1">Semua jurnal {year} lolos kontrol otomatis.</p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {shown.map((e, i) => {
                            const sev = SEV[e.severity] || SEV.low;
                            return (
                                <div key={i} className="card flex flex-wrap items-start gap-3">
                                    <span className={`mt-1 w-2 h-2 rounded-full shrink-0 ${sev.dot}`} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-white font-medium">{e.title}</span>
                                            <span className={`badge ${sev.cls}`}>{sev.label}</span>
                                            <span className="font-mono text-xs text-slate-400">{e.entry_no}</span>
                                            <span className="text-slate-500 text-xs">{tgl(e.entry_date)}</span>
                                        </div>
                                        <p className="text-slate-300 text-sm mt-1">{e.detail}</p>
                                        {e.description && <p className="text-slate-500 text-xs mt-0.5">Jurnal: {e.description}</p>}
                                    </div>
                                    <Link href={route('books.journal.index')} className="text-blue-400 hover:text-blue-300 text-xs print:hidden">
                                        Buka Jurnal →
                                    </Link>
                                </div>
                            );
                        })}
                    </div>
                )}
            </AppLayout>
        </>
    );
}
