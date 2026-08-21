import { Head, useForm, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

export default function Vendors({ vendors, can_manage }) {
    const [editing, setEditing] = useState(null); // null = tertutup, {} = baru, {..} = edit
    const { data, setData, post, put, processing, reset, errors } = useForm({
        code: '', name: '', phone: '', address: '', payable_balance: 0, is_active: true,
    });

    const openNew = () => { reset(); setData({ code: '', name: '', phone: '', address: '', payable_balance: 0, is_active: true }); setEditing({}); };
    const openEdit = (v) => { setData({ code: v.code, name: v.name, phone: v.phone || '', address: v.address || '', payable_balance: v.opening_balance || 0, is_active: v.is_active }); setEditing(v); };

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { reset(); setEditing(null); } };
        if (editing && editing.id) put(route('books.vendors.update', editing.id), opts);
        else post(route('books.vendors.store'), opts);
    };

    return (
        <>
            <Head title="Master Vendor" />
            <AppLayout title="Master Vendor (Kode Bantu)">
                <div className="flex items-center gap-3 mb-4">
                    <p className="text-slate-400 text-sm flex-1">Kode vendor dipakai sebagai <b>kode bantu</b> pada baris jurnal.</p>
                    {can_manage && <button onClick={openNew} className="btn-primary">+ Vendor</button>}
                </div>

                {editing && (
                    <form onSubmit={submit} className="card mb-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label className="label">Kode</label>
                            <input className="input" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="V-001" required />
                            {errors.code && <p className="text-red-400 text-xs mt-1">{errors.code}</p>}
                        </div>
                        <div>
                            <label className="label">Nama Vendor</label>
                            <input className="input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        </div>
                        <div>
                            <label className="label">Telepon</label>
                            <input className="input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Alamat</label>
                            <input className="input" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Saldo Utang Awal (Rp)</label>
                            <input type="number" step="0.01" className="input" value={data.payable_balance} onChange={(e) => setData('payable_balance', e.target.value)} />
                        </div>
                        <label className="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Aktif
                        </label>
                        <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-2">
                            <button type="button" onClick={() => setEditing(null)} className="btn-secondary">Batal</button>
                            <button type="submit" className="btn-primary" disabled={processing}>{editing.id ? 'Simpan Perubahan' : 'Simpan'}</button>
                        </div>
                    </form>
                )}

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Kode</th>
                                <th className="table-header">Nama</th>
                                <th className="table-header">Telepon</th>
                                <th className="table-header">Alamat</th>
                                <th className="table-header text-right">Saldo Utang</th>
                                <th className="table-header">Status</th>
                                <th className="table-header"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {vendors.length === 0 && (
                                <tr><td colSpan={7} className="table-cell text-center text-slate-400">Belum ada vendor.</td></tr>
                            )}
                            {vendors.map((v) => (
                                <tr key={v.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{v.code}</td>
                                    <td className="table-cell">{v.name}</td>
                                    <td className="table-cell text-slate-400">{v.phone || '—'}</td>
                                    <td className="table-cell text-slate-400">{v.address || '—'}</td>
                                    <td className="table-cell text-right font-medium text-amber-300" title={`Awal ${fmt(v.opening_balance)} + mutasi ${fmt(v.movement)}`}>{fmt(v.current_balance)}</td>
                                    <td className="table-cell">
                                        <span className={`badge ${v.is_active ? 'badge-green' : 'badge-slate'}`}>{v.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                    </td>
                                    <td className="table-cell whitespace-nowrap">
                                        <Link href={route('books.vendors.show', v.id)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Riwayat</Link>
                                        {can_manage && (
                                            <>
                                                <button onClick={() => openEdit(v)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Edit</button>
                                                <button onClick={() => router.delete(route('books.vendors.destroy', v.id), { preserveScroll: true })}
                                                    className="text-red-400 hover:text-red-300 text-xs">Hapus</button>
                                            </>
                                        )}
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
