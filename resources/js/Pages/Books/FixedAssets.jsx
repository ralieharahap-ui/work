import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => (Number(n) ? new Intl.NumberFormat('id-ID').format(Math.round(n)) : '—');
const tgl = (s) => (s ? new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');

export default function FixedAssets({ assets, as_of, accounts, totals, can_manage, depreciation }) {
    const [editing, setEditing] = useState(null); // null | {} baru | {..} edit
    const [date, setDate] = useState(as_of);
    const [period, setPeriod] = useState(depreciation?.current_period ?? '');

    const postedSet = new Set(depreciation?.posted_periods ?? []);
    const alreadyPosted = postedSet.has(period);

    const postDepreciation = () => {
        if (!period) return;
        router.post(route('books.fixed-assets.depreciate'), { period }, { preserveScroll: true });
    };

    const { data, setData, post, put, processing, reset, errors } = useForm({
        description: '', purchase_date: new Date().toISOString().slice(0, 10),
        qty: 1, unit_cost: '', residual_value: 0, useful_life_months: '', account_id: '', notes: '',
    });

    const openNew = () => {
        reset();
        setData({ description: '', purchase_date: new Date().toISOString().slice(0, 10), qty: 1, unit_cost: '', residual_value: 0, useful_life_months: '', account_id: '', notes: '' });
        setEditing({});
    };
    const openEdit = (a) => {
        setData({
            description: a.description, purchase_date: a.purchase_date, qty: a.qty,
            unit_cost: a.unit_cost, residual_value: a.residual_value, useful_life_months: a.useful_life_months,
            account_id: a.account_id || '', notes: a.notes || '',
        });
        setEditing(a);
    };

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { reset(); setEditing(null); } };
        if (editing && editing.id) put(route('books.fixed-assets.update', editing.id), opts);
        else post(route('books.fixed-assets.store'), opts);
    };

    return (
        <>
            <Head title="Daftar Aset" />
            <AppLayout title="Daftar Aset Tetap & Penyusutan">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Per Tanggal</label>
                        <input type="date" className="input w-auto" value={date}
                            onChange={(e) => setDate(e.target.value)}
                            onBlur={() => router.get(route('books.fixed-assets.index'), { as_of: date }, { preserveState: true })} />
                    </div>
                    <div className="flex-1" />
                    {can_manage && <button onClick={openNew} className="btn-primary">+ Aset Baru</button>}
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                {/* Auto-jurnal penyusutan bulanan (Tahap 5-6 automasi) */}
                {can_manage && depreciation && (
                    <div className="card mb-5 print:hidden border border-sky-500/30 bg-sky-500/5">
                        <div className="flex flex-wrap items-end gap-3">
                            <div>
                                <p className="text-sky-300 font-semibold text-sm">⚙️ Auto-Jurnal Penyusutan</p>
                                <p className="text-slate-400 text-xs mt-0.5">
                                    Posting D <span className="font-mono">6112</span> Beban Penyusutan | K <span className="font-mono">1609</span> Akumulasi Penyusutan.
                                    Estimasi/bulan: <span className="text-slate-200">{fmt(depreciation.monthly_total)}</span>
                                </p>
                            </div>
                            <div className="flex-1" />
                            <div>
                                <label className="label">Periode</label>
                                <input type="month" className="input w-auto" value={period} onChange={(e) => setPeriod(e.target.value)} />
                            </div>
                            <button
                                onClick={postDepreciation}
                                disabled={!depreciation.has_accounts || alreadyPosted || !period}
                                className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                title={!depreciation.has_accounts ? 'Akun 6112/1609 belum ada' : alreadyPosted ? 'Periode ini sudah diposting' : ''}
                            >
                                {alreadyPosted ? '✓ Sudah Diposting' : 'Posting Penyusutan'}
                            </button>
                        </div>
                        {!depreciation.has_accounts && (
                            <p className="text-amber-300 text-xs mt-2">Akun 6112 (Beban Penyusutan) atau 1609 (Akumulasi Penyusutan) belum ada di COA.</p>
                        )}
                        {depreciation.posted_periods.length > 0 && (
                            <div className="mt-3 flex flex-wrap items-center gap-1.5">
                                <span className="text-slate-500 text-xs">Sudah diposting:</span>
                                {depreciation.posted_periods.map((p) => (
                                    <span key={p} className="badge badge-green text-[10px]">{p}</span>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {can_manage && editing && (
                    <form onSubmit={submit} className="card mb-5 print:hidden grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div className="sm:col-span-2 lg:col-span-3">
                            <label className="label">Nama / Deskripsi Aset</label>
                            <input type="text" className="input" value={data.description} onChange={(e) => setData('description', e.target.value)} required />
                            {errors.description && <p className="text-red-400 text-xs mt-1">{errors.description}</p>}
                        </div>
                        <div><label className="label">Tanggal Beli</label>
                            <input type="date" className="input" value={data.purchase_date} onChange={(e) => setData('purchase_date', e.target.value)} required /></div>
                        <div><label className="label">QTY</label>
                            <input type="number" min="1" className="input" value={data.qty} onChange={(e) => setData('qty', e.target.value)} required /></div>
                        <div><label className="label">Harga / Unit</label>
                            <input type="number" step="0.01" min="0" className="input" value={data.unit_cost} onChange={(e) => setData('unit_cost', e.target.value)} required /></div>
                        <div><label className="label">Nilai Sisa</label>
                            <input type="number" step="0.01" min="0" className="input" value={data.residual_value} onChange={(e) => setData('residual_value', e.target.value)} required /></div>
                        <div><label className="label">Umur Ekonomis (bulan)</label>
                            <input type="number" min="1" className="input" value={data.useful_life_months} onChange={(e) => setData('useful_life_months', e.target.value)} required /></div>
                        <div><label className="label">Akun Aset (opsional)</label>
                            <select className="input" value={data.account_id} onChange={(e) => setData('account_id', e.target.value)}>
                                <option value="">— tidak dikaitkan —</option>
                                {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                            </select></div>
                        <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-2">
                            <button type="button" onClick={() => setEditing(null)} className="btn-secondary">Batal</button>
                            <button type="submit" className="btn-primary" disabled={processing}>{editing.id ? 'Simpan Perubahan' : 'Simpan Aset'}</button>
                        </div>
                    </form>
                )}

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Nama Aset</th>
                                <th className="table-header">Tgl Beli</th>
                                <th className="table-header text-right">QTY</th>
                                <th className="table-header text-right">Harga Perolehan</th>
                                <th className="table-header text-right">Nilai Sisa</th>
                                <th className="table-header text-right">UE (bln)</th>
                                <th className="table-header text-right">Penyusutan /bln</th>
                                <th className="table-header text-right">Bln Berjalan</th>
                                <th className="table-header text-right">Akum. Penyusutan</th>
                                <th className="table-header text-right">Nilai Buku</th>
                                {can_manage && <th className="table-header print:hidden"></th>}
                            </tr>
                        </thead>
                        <tbody>
                            {assets.length === 0 && (
                                <tr><td colSpan={can_manage ? 11 : 10} className="table-cell text-center text-slate-400">Belum ada aset.</td></tr>
                            )}
                            {assets.map((a) => (
                                <tr key={a.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell">{a.description}
                                        {a.account && <span className="block text-slate-500 text-xs">{a.account}</span>}
                                    </td>
                                    <td className="table-cell whitespace-nowrap">{tgl(a.purchase_date)}</td>
                                    <td className="table-cell text-right">{a.qty}</td>
                                    <td className="table-cell text-right">{fmt(a.total_cost)}</td>
                                    <td className="table-cell text-right">{fmt(a.residual_value)}</td>
                                    <td className="table-cell text-right">{a.useful_life_months}</td>
                                    <td className="table-cell text-right">{fmt(a.monthly)}</td>
                                    <td className="table-cell text-right">{a.elapsed_months}</td>
                                    <td className="table-cell text-right">{fmt(a.accumulated)}</td>
                                    <td className="table-cell text-right font-medium text-blue-300">{fmt(a.book_value)}</td>
                                    {can_manage && (
                                        <td className="table-cell print:hidden whitespace-nowrap">
                                            <button onClick={() => openEdit(a)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Edit</button>
                                            <button onClick={() => router.delete(route('books.fixed-assets.destroy', a.id), { preserveScroll: true })}
                                                className="text-red-400 hover:text-red-300 text-xs">Hapus</button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-slate-600">
                                <td colSpan={3} className="table-cell font-medium">Total</td>
                                <td className="table-cell text-right font-bold">{fmt(totals.total_cost)}</td>
                                <td colSpan={4}></td>
                                <td className="table-cell text-right font-bold">{fmt(totals.accumulated)}</td>
                                <td className="table-cell text-right font-bold text-blue-300">{fmt(totals.book_value)}</td>
                                {can_manage && <td className="print:hidden"></td>}
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
