import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(n);

export default function TrialBalance({ accounts, total_debit, total_credit, is_balanced, as_of }) {
    const [date, setDate] = useState(as_of);

    return (
        <>
            <Head title="Neraca Saldo" />
            <AppLayout title="Neraca Saldo (Trial Balance)">
                <div className="flex items-center gap-3 mb-4">
                    <div>
                        <label className="label">Per Tanggal</label>
                        <input type="date" className="input w-auto" value={date} onChange={e => setDate(e.target.value)}
                            onBlur={() => router.get(route('books.trial-balance'), { as_of: date })}/>
                    </div>
                    {is_balanced
                        ? <span className="text-xs bg-green-900 text-green-300 px-3 py-1.5 rounded-full mt-5">✓ Seimbang</span>
                        : <span className="text-xs bg-red-900 text-red-300 px-3 py-1.5 rounded-full mt-5">✗ Tidak Seimbang</span>
                    }
                </div>

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Kode</th>
                                <th className="table-header">Nama Akun</th>
                                <th className="table-header">Tipe</th>
                                <th className="table-header text-right">Debit</th>
                                <th className="table-header text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.map((acc) => (
                                <tr key={acc.code} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{acc.code}</td>
                                    <td className="table-cell">{acc.name}</td>
                                    <td className="table-cell text-slate-400 capitalize">{acc.type}</td>
                                    <td className="table-cell text-right">{acc.debit > 0 ? fmt(acc.debit) : '—'}</td>
                                    <td className="table-cell text-right">{acc.credit > 0 ? fmt(acc.credit) : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-slate-600">
                                <td colSpan={3} className="table-cell font-medium">Total</td>
                                <td className={`table-cell text-right font-bold ${is_balanced ? 'text-green-400' : 'text-red-400'}`}>{fmt(total_debit)}</td>
                                <td className={`table-cell text-right font-bold ${is_balanced ? 'text-green-400' : 'text-red-400'}`}>{fmt(total_credit)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
