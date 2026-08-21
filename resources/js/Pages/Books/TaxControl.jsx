import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => (Math.round(Number(n) || 0) === 0 ? '—' : new Intl.NumberFormat('id-ID').format(Math.round(Number(n))));
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export default function TaxControl({ year, ppn, pph_terutang, pph_dimuka }) {
    const [yr, setYr] = useState(year);

    const totalTerutang = pph_terutang.reduce((s, r) => s + Number(r.total || 0), 0);
    const totalDimuka = pph_dimuka.reduce((s, r) => s + Number(r.total || 0), 0);

    return (
        <>
            <Head title="Kontrol Pajak" />
            <AppLayout title="Kontrol Pajak — PPN & PPh">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Tahun</label>
                        <input type="number" className="input w-28" value={yr}
                            onChange={(e) => setYr(e.target.value)}
                            onBlur={() => router.get(route('books.tax-control'), { year: yr }, { preserveState: true })} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                {/* Ringkasan KPI */}
                <div className="grid gap-3 sm:grid-cols-3 mb-5">
                    <div className="card">
                        <p className="text-slate-400 text-xs">PPN Kurang / (Lebih) Bayar {year}</p>
                        <p className={`text-2xl font-bold mt-1 ${ppn.total_kurang_bayar >= 0 ? 'text-amber-300' : 'text-emerald-300'}`}>
                            {fmt(ppn.total_kurang_bayar)}
                        </p>
                        <p className="text-slate-500 text-[11px] mt-1">Keluaran {fmt(ppn.total_keluaran)} − Masukan {fmt(ppn.total_masukan)}</p>
                    </div>
                    <div className="card">
                        <p className="text-slate-400 text-xs">PPh Dipotong/Dipungut — Terutang</p>
                        <p className="text-2xl font-bold mt-1 text-purple-300">{fmt(totalTerutang)}</p>
                        <p className="text-slate-500 text-[11px] mt-1">Kewajiban setor PPh 21/22/23/Final</p>
                    </div>
                    <div className="card">
                        <p className="text-slate-400 text-xs">PPh Dibayar Dimuka — Kredit Pajak</p>
                        <p className="text-2xl font-bold mt-1 text-sky-300">{fmt(totalDimuka)}</p>
                        <p className="text-slate-500 text-[11px] mt-1">PPh 22/23 dapat diperhitungkan</p>
                    </div>
                </div>

                {/* PPN bulanan */}
                <div className="card overflow-x-auto mb-5">
                    <h2 className="text-white font-semibold mb-3">Rekonsiliasi PPN per Bulan</h2>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Bulan</th>
                                <th className="table-header text-right">PPN Masukan (1501)</th>
                                <th className="table-header text-right">PPN Keluaran (2201)</th>
                                <th className="table-header text-right">Kurang / (Lebih) Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ppn.monthly.map((m) => (
                                <tr key={m.month} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell">{m.label}</td>
                                    <td className="table-cell text-right">{fmt(m.masukan)}</td>
                                    <td className="table-cell text-right">{fmt(m.keluaran)}</td>
                                    <td className={`table-cell text-right ${m.kurang_bayar < 0 ? 'text-emerald-300' : ''}`}>{fmt(m.kurang_bayar)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-slate-600 font-bold">
                                <td className="table-cell">Total {year}</td>
                                <td className="table-cell text-right text-sky-300">{fmt(ppn.total_masukan)}</td>
                                <td className="table-cell text-right text-amber-300">{fmt(ppn.total_keluaran)}</td>
                                <td className="table-cell text-right text-blue-300">{fmt(ppn.total_kurang_bayar)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {/* PPh terutang & dibayar dimuka */}
                <div className="grid gap-5 lg:grid-cols-2">
                    <PphTable title="PPh Terutang (kewajiban setor)" rows={pph_terutang} year={year} accent="text-purple-300" />
                    <PphTable title="PPh Dibayar Dimuka (kredit pajak)" rows={pph_dimuka} year={year} accent="text-sky-300" />
                </div>
            </AppLayout>
        </>
    );
}

function PphTable({ title, rows, year, accent }) {
    return (
        <div className="card overflow-x-auto">
            <h2 className="text-white font-semibold mb-3">{title}</h2>
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-slate-700">
                        <th className="table-header">Akun</th>
                        {MONTHS.map((m) => <th key={m} className="table-header text-right !px-1.5 !text-[10px]">{m}</th>)}
                        <th className="table-header text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.code} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                            <td className="table-cell whitespace-nowrap"><span className="font-mono text-xs text-slate-400">{r.code}</span> {r.name}</td>
                            {r.months.map((v, i) => <td key={i} className="px-1.5 py-2 text-right text-[11px]">{fmt(v)}</td>)}
                            <td className={`table-cell text-right font-bold ${accent}`}>{fmt(r.total)}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t border-slate-600 font-bold">
                        <td className="table-cell">Total {year}</td>
                        {MONTHS.map((_, i) => (
                            <td key={i} className="px-1.5 py-2 text-right text-[11px] text-slate-400">
                                {fmt(rows.reduce((s, r) => s + Number(r.months[i] || 0), 0))}
                            </td>
                        ))}
                        <td className={`table-cell text-right ${accent}`}>
                            {fmt(rows.reduce((s, r) => s + Number(r.total || 0), 0))}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
