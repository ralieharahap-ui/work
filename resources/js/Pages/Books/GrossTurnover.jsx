import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

export default function GrossTurnover({ months, year, rate, total_gross, total_tax }) {
    const [yr, setYr] = useState(year);

    return (
        <>
            <Head title="Rekap Peredaran Bruto" />
            <AppLayout title="Rekap Peredaran Bruto & PPh Final UMKM">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Tahun</label>
                        <input type="number" className="input w-28" value={yr}
                            onChange={(e) => setYr(e.target.value)}
                            onBlur={() => router.get(route('books.gross-turnover'), { year: yr }, { preserveState: true })} />
                    </div>
                    <div className="flex items-center">
                        <span className="badge badge-purple mt-5">Tarif PPh Final {(rate * 100).toLocaleString('id-ID')}%</span>
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Bulan</th>
                                <th className="table-header text-right">Peredaran Bruto</th>
                                <th className="table-header text-right">PPh Final ({(rate * 100).toLocaleString('id-ID')}%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {months.map((m) => (
                                <tr key={m.month} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell">{m.label}</td>
                                    <td className="table-cell text-right">{m.gross ? fmt(m.gross) : '—'}</td>
                                    <td className="table-cell text-right">{m.tax ? fmt(m.tax) : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-slate-600">
                                <td className="table-cell font-medium">Total {year}</td>
                                <td className="table-cell text-right font-bold text-blue-300">{fmt(total_gross)}</td>
                                <td className="table-cell text-right font-bold text-purple-300">{fmt(total_tax)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
