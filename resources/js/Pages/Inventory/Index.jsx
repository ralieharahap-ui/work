import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import { PlusIcon, AdjustmentsHorizontalIcon } from '@heroicons/react/24/outline';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(n);

export default function Inventory({ items, can }) {
    const [showAdd, setShowAdd]   = useState(false);
    const [adjustItem, setAdjust] = useState(null);

    const form = useForm({ sku:'', name:'', unit:'ton', unit_price:'', cost_price:'', stock_min:'', warehouse_loc:'' });
    const adj  = useForm({ qty_change:'', reason:'' });

    const submit = (e) => { e.preventDefault(); form.post(route('inventory.store'), { onSuccess: () => { form.reset(); setShowAdd(false); }}); };
    const submitAdj = (e) => {
        e.preventDefault();
        adj.post(route('inventory.adjust', adjustItem.id), { onSuccess: () => { adj.reset(); setAdjust(null); }});
    };

    return (
        <>
            <Head title="Inventory" />
            <AppLayout title="Inventory">
                <div className="flex justify-between items-center mb-4">
                    <p className="text-slate-400 text-sm">{items.total} item terdaftar</p>
                    {can?.create && (
                        <button onClick={() => setShowAdd(true)} className="btn-primary flex items-center gap-2">
                            <PlusIcon className="w-4 h-4" /> Tambah Item
                        </button>
                    )}
                </div>

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                {['SKU','Nama','Unit','Harga Jual','Stok','Min Stok','Lokasi','Aksi'].map(h => (
                                    <th key={h} className="table-header">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {items.data.map((item) => (
                                <tr key={item.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{item.sku}</td>
                                    <td className="table-cell font-medium">{item.name}</td>
                                    <td className="table-cell text-slate-400">{item.unit}</td>
                                    <td className="table-cell">Rp {fmt(item.unit_price)}</td>
                                    <td className={`table-cell font-medium ${item.is_low_stock ? 'text-red-400' : 'text-green-400'}`}>
                                        {fmt(item.stock_qty)}
                                    </td>
                                    <td className="table-cell text-slate-400">{fmt(item.stock_min)}</td>
                                    <td className="table-cell text-slate-400">{item.warehouse_loc ?? '—'}</td>
                                    <td className="table-cell">
                                        {can?.edit && (
                                            <button onClick={() => setAdjust(item)} className="text-blue-400 hover:text-blue-300 text-xs flex items-center gap-1">
                                                <AdjustmentsHorizontalIcon className="w-3 h-3" /> Sesuaikan
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Modal Tambah Item */}
                {showAdd && (
                    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
                        <div className="bg-slate-800 rounded-xl p-6 w-full max-w-lg border border-slate-700">
                            <h2 className="text-white font-medium mb-4">Tambah Item Baru</h2>
                            <form onSubmit={submit} className="space-y-3">
                                <div className="grid grid-cols-2 gap-3">
                                    <div><label className="label">SKU</label><input className="input" value={form.data.sku} onChange={e=>form.setData('sku',e.target.value)} placeholder="BIO-0001"/></div>
                                    <div><label className="label">Satuan</label><input className="input" value={form.data.unit} onChange={e=>form.setData('unit',e.target.value)} placeholder="ton"/></div>
                                </div>
                                <div><label className="label">Nama Item</label><input className="input" value={form.data.name} onChange={e=>form.setData('name',e.target.value)} placeholder="Cangkang Sawit (PKS)"/></div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div><label className="label">Harga Jual</label><input className="input" type="number" value={form.data.unit_price} onChange={e=>form.setData('unit_price',e.target.value)}/></div>
                                    <div><label className="label">HPP</label><input className="input" type="number" value={form.data.cost_price} onChange={e=>form.setData('cost_price',e.target.value)}/></div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div><label className="label">Stok Minimum</label><input className="input" type="number" value={form.data.stock_min} onChange={e=>form.setData('stock_min',e.target.value)}/></div>
                                    <div><label className="label">Lokasi Gudang</label><input className="input" value={form.data.warehouse_loc} onChange={e=>form.setData('warehouse_loc',e.target.value)} placeholder="Gudang A"/></div>
                                </div>
                                <div className="flex gap-3 pt-2">
                                    <button type="submit" disabled={form.processing} className="btn-primary">Simpan</button>
                                    <button type="button" onClick={() => setShowAdd(false)} className="btn-secondary">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Modal Sesuaikan Stok */}
                {adjustItem && (
                    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
                        <div className="bg-slate-800 rounded-xl p-6 w-full max-w-sm border border-slate-700">
                            <h2 className="text-white font-medium mb-1">Sesuaikan Stok</h2>
                            <p className="text-slate-400 text-sm mb-4">{adjustItem.name} — Stok saat ini: <span className="text-white">{fmt(adjustItem.stock_qty)} {adjustItem.unit}</span></p>
                            <form onSubmit={submitAdj} className="space-y-3">
                                <div>
                                    <label className="label">Perubahan Qty (+ masuk / - keluar)</label>
                                    <input className="input" type="number" step="0.001" value={adj.data.qty_change} onChange={e=>adj.setData('qty_change',e.target.value)} placeholder="-10 atau +50"/>
                                </div>
                                <div>
                                    <label className="label">Alasan</label>
                                    <input className="input" value={adj.data.reason} onChange={e=>adj.setData('reason',e.target.value)} placeholder="Penyesuaian fisik / kerusakan"/>
                                </div>
                                <div className="flex gap-3 pt-2">
                                    <button type="submit" disabled={adj.processing} className="btn-primary">Simpan</button>
                                    <button type="button" onClick={() => setAdjust(null)} className="btn-secondary">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
