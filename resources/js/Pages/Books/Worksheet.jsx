import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => (Number(n) ? new Intl.NumberFormat('id-ID').format(Math.round(n)) : '—');

export default function Worksheet({ accounts, as_of, net_income, totals }) {
    const [date, setDate] = useState(as_of);

    return (
        <>
            <Head title="Neraca Lajur" />
            <AppLayout title="Neraca Lajur (Worksheet)">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Per Tanggal</label>
                        <input type="date" className="input w-auto" value={date}
                            onChange={(e) => setDate(e.target.value)}
                            onBlur={() => router.get(route('books.worksheet'), { as_of: date }, { preserveState: true })} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="card overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr>
                                <th className="table-header" rowSpan={2}>Kode</th>
                                <th className="table-header" rowSpan={2}>Nama Akun</th>
                                <th className="table-header text-center border-l border-slate-700" colSpan={2}>Neraca Saldo</th>
                                <th className="table-header text-center border-l border-slate-700" colSpan={2}>Laba/Rugi</th>
                                <th className="table-header text-center border-l border-slate-700" colSpan={2}>Neraca</th>
                            </tr>
                            <tr className="border-b border-slate-700">
                                <th className="table-header text-right border-l border-slate-700">Debet</th>
                                <th className="table-header text-right">Kredit</th>
                                <th className="table-header text-right border-l border-slate-700">Debet</th>
                                <th className="table-header text-right">Kredit</th>
                                <th className="table-header text-right border-l border-slate-700">Debet</th>
                                <th className="table-header text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.map((a) => (
                                <tr key={a.code} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{a.code}</td>
                                    <td className="table-cell">{a.name}</td>
                                    <td className="table-cell text-right border-l border-slate-700/50">{fmt(a.tb_debit)}</td>
                                    <td className="table-cell text-right">{fmt(a.tb_credit)}</td>
                                    <td className="table-cell text-right border-l border-slate-700/50">{fmt(a.lr_debit)}</td>
                                    <td className="table-cell text-right">{fmt(a.lr_credit)}</td>
                                    <td className="table-cell text-right border-l border-slate-700/50">{fmt(a.nrc_debit)}</td>
                                    <td className="table-cell text-right">{fmt(a.nrc_credit)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-slate-600">
                                <td colSpan={2} className="table-cell font-medium">Subtotal</td>
                                <td className="table-cell text-right font-bold border-l border-slate-700/50">{fmt(totals.tb_debit)}</td>
                                <td className="table-cell text-right font-bold">{fmt(totals.tb_credit)}</td>
                                <td className="table-cell text-right font-bold border-l border-slate-700/50">{fmt(totals.lr_debit)}</td>
                                <td className="table-cell text-right font-bold">{fmt(totals.lr_credit)}</td>
                                <td className="table-cell text-right font-bold border-l border-slate-700/50">{fmt(totals.nrc_debit)}</td>
                                <td className="table-cell text-right font-bold">{fmt(totals.nrc_credit)}</td>
                            </tr>
                            <tr>
                                <td colSpan={2} className="table-cell font-medium">
                                    {net_income >= 0 ? 'Laba Bersih' : 'Rugi Bersih'}
                                </td>
                                <td colSpan={2}></td>
                                <td className="table-cell text-right border-l border-slate-700/50">{net_income >= 0 ? fmt(net_income) : '—'}</td>
                                <td className="table-cell text-right">{net_income < 0 ? fmt(-net_income) : '—'}</td>
                                <td className="table-cell text-right border-l border-slate-700/50">{net_income < 0 ? fmt(-net_income) : '—'}</td>
                                <td className="table-cell text-right">{net_income >= 0 ? fmt(net_income) : '—'}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
