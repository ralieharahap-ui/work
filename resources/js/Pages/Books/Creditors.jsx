import { Head, useForm, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
const tgl = (s) => s ? new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

export default function Creditors({ creditors, summary, can_manage }) {
    const [editing, setEditing] = useState(null); // null = tertutup, {} = baru, {..} = edit
    const { data, setData, post, put, processing, reset, errors } = useForm({
        code: '', name: '', category: 'bank', phone: '', address: '', account_no: '',
        interest_rate: '', loan_ceiling: '', maturity_date: '', payable_balance: 0, is_active: true,
    });

    const blank = { code: '', name: '', category: 'bank', phone: '', address: '', account_no: '', interest_rate: '', loan_ceiling: '', maturity_date: '', payable_balance: 0, is_active: true };

    const openNew = () => { reset(); setData(blank); setEditing({}); };
    const openEdit = (c) => {
        setData({
            code: c.code, name: c.name, category: c.category, phone: c.phone || '', address: c.address || '',
            account_no: c.account_no || '', interest_rate: c.interest_rate || '', loan_ceiling: c.loan_ceiling || '',
            maturity_date: c.maturity_date || '', payable_balance: c.opening_balance || 0, is_active: c.is_active,
        });
        setEditing(c);
    };

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { reset(); setEditing(null); } };
        if (editing && editing.id) put(route('books.creditors.update', editing.id), opts);
        else post(route('books.creditors.store'), opts);
    };

    return (
        <>
            <Head title="Kreditur Pendanaan" />
            <AppLayout title="Kreditur Pendanaan (Investor & Bank)">
                <div className="grid sm:grid-cols-3 gap-3 mb-4">
                    <div className="card">
                        <p className="text-slate-400 text-xs">Total Kewajiban ke Investor</p>
                        <p className="text-amber-300 font-bold text-lg mt-1">{fmt(summary.total_investor)}</p>
                    </div>
                    <div className="card">
                        <p className="text-slate-400 text-xs">Total Kewajiban ke Bank</p>
                        <p className="text-amber-300 font-bold text-lg mt-1">{fmt(summary.total_bank)}</p>
                    </div>
                    <div className="card">
                        <p className="text-slate-400 text-xs">Jatuh Tempo &lt; 30 Hari</p>
                        <p className={`font-bold text-lg mt-1 ${summary.due_soon_count > 0 ? 'text-red-400' : 'text-slate-200'}`}>{summary.due_soon_count}</p>
                    </div>
                </div>

                <div className="flex items-center gap-3 mb-4">
                    <p className="text-slate-400 text-sm flex-1">Kode kreditur dipakai sebagai <b>kode bantu</b> pada baris jurnal kewajiban pendanaan.</p>
                    {can_manage && <button onClick={openNew} className="btn-primary">+ Kreditur</button>}
                </div>

                {editing && (
                    <form onSubmit={submit} className="card mb-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label className="label">Kode</label>
                            <input className="input" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="BANK-001" required />
                            {errors.code && <p className="text-red-400 text-xs mt-1">{errors.code}</p>}
                        </div>
                        <div>
                            <label className="label">Nama Kreditur</label>
                            <input className="input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        </div>
                        <div>
                            <label className="label">Kategori</label>
                            <select className="input" value={data.category} onChange={(e) => setData('category', e.target.value)}>
                                <option value="bank">Bank</option>
                                <option value="investor">Investor</option>
                            </select>
                        </div>
                        <div>
                            <label className="label">No. Rekening</label>
                            <input className="input" value={data.account_no} onChange={(e) => setData('account_no', e.target.value)} />
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
                            <label className="label">Bunga (% / tahun)</label>
                            <input type="number" step="0.01" className="input" value={data.interest_rate} onChange={(e) => setData('interest_rate', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Plafon Pinjaman (Rp)</label>
                            <input type="number" step="0.01" className="input" value={data.loan_ceiling} onChange={(e) => setData('loan_ceiling', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Jatuh Tempo</label>
                            <input type="date" className="input" value={data.maturity_date} onChange={(e) => setData('maturity_date', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Saldo Kewajiban Awal (Rp)</label>
                            <input type="number" step="0.01" className="input" value={data.payable_balance} onChange={(e) => setData('payable_balance', e.target.value)} />
                        </div>
                        <label className="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Aktif
                        </label>
                        <div className="sm:col-span-2 lg:col-span-4 flex justify-end gap-2">
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
                                <th className="table-header">Kategori</th>
                                <th className="table-header">Jatuh Tempo</th>
                                <th className="table-header">Bunga</th>
                                <th className="table-header text-right">Saldo Kewajiban</th>
                                <th className="table-header">Status</th>
                                <th className="table-header"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {creditors.length === 0 && (
                                <tr><td colSpan={8} className="table-cell text-center text-slate-400">Belum ada kreditur pendanaan.</td></tr>
                            )}
                            {creditors.map((c) => (
                                <tr key={c.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{c.code}</td>
                                    <td className="table-cell">{c.name}</td>
                                    <td className="table-cell">
                                        <span className={`badge ${c.category === 'investor' ? 'badge-blue' : 'badge-amber'} capitalize`}>{c.category}</span>
                                    </td>
                                    <td className={`table-cell text-xs ${c.due_soon ? 'text-red-400 font-medium' : 'text-slate-400'}`}>{tgl(c.maturity_date)}</td>
                                    <td className="table-cell text-slate-400 text-xs">{c.interest_rate ? `${c.interest_rate}%/th` : '—'}</td>
                                    <td className="table-cell text-right font-medium text-amber-300" title={`Awal ${fmt(c.opening_balance)} + mutasi ${fmt(c.movement)}`}>{fmt(c.current_balance)}</td>
                                    <td className="table-cell">
                                        <span className={`badge ${c.is_active ? 'badge-green' : 'badge-slate'}`}>{c.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                    </td>
                                    <td className="table-cell whitespace-nowrap">
                                        <Link href={route('books.creditors.show', c.id)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Riwayat</Link>
                                        {can_manage && (
                                            <>
                                                <button onClick={() => openEdit(c)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Edit</button>
                                                <button onClick={() => router.delete(route('books.creditors.destroy', c.id), { preserveScroll: true })}
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
