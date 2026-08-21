import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

export default function Customers({ customers, can_manage }) {
    const [editing, setEditing] = useState(null); // customer row being edited
    const { data, setData, put, processing, errors, reset } = useForm({ code: '', receivable_balance: 0 });

    const openEdit = (c) => { setData({ code: c.code || '', receivable_balance: c.opening_balance || 0 }); setEditing(c); };
    const submit = (e) => {
        e.preventDefault();
        put(route('books.customers.update', editing.id), { preserveScroll: true, onSuccess: () => { reset(); setEditing(null); } });
    };

    return (
        <>
            <Head title="Master Customer" />
            <AppLayout title="Master Customer (Piutang)">
                <p className="text-slate-400 text-sm mb-4">
                    Data customer bersumber dari <b>Titik Bongkar</b>. Beri <b>Kode Bantu</b> agar saldo piutang otomatis
                    terhitung dari jurnal Posted (baris akun Piutang yang diberi kode bantu = kode customer).
                </p>

                {can_manage && editing && (
                    <form onSubmit={submit} className="card mb-5 grid sm:grid-cols-3 gap-3">
                        <div className="sm:col-span-3 text-white font-medium">{editing.name}</div>
                        <div>
                            <label className="label">Kode Bantu Customer</label>
                            <input className="input" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="C-001" />
                            {errors.code && <p className="text-red-400 text-xs mt-1">{errors.code}</p>}
                        </div>
                        <div>
                            <label className="label">Saldo Piutang Awal (Rp)</label>
                            <input type="number" step="0.01" className="input" value={data.receivable_balance} onChange={(e) => setData('receivable_balance', e.target.value)} />
                        </div>
                        <div className="flex items-end gap-2">
                            <button type="button" onClick={() => setEditing(null)} className="btn-secondary">Batal</button>
                            <button type="submit" className="btn-primary" disabled={processing}>Simpan</button>
                        </div>
                    </form>
                )}

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Kode</th>
                                <th className="table-header">Customer</th>
                                <th className="table-header">Kota</th>
                                <th className="table-header text-right">Saldo Piutang</th>
                                <th className="table-header"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {customers.length === 0 && (
                                <tr><td colSpan={5} className="table-cell text-center text-slate-400">Belum ada customer.</td></tr>
                            )}
                            {customers.map((c) => (
                                <tr key={c.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{c.code || <span className="text-slate-500">—</span>}</td>
                                    <td className="table-cell">{c.name}</td>
                                    <td className="table-cell text-slate-400">{c.city || '—'}</td>
                                    <td className="table-cell text-right font-medium text-emerald-300" title={`Awal ${fmt(c.opening_balance)} + mutasi ${fmt(c.movement)}`}>{fmt(c.current_balance)}</td>
                                    <td className="table-cell whitespace-nowrap">
                                        <Link href={route('books.customers.show', c.id)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Riwayat</Link>
                                        {can_manage && <button onClick={() => openEdit(c)} className="text-blue-400 hover:text-blue-300 text-xs">Set Kode</button>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
