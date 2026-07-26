import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import { PlusIcon, MapPinIcon, TrashIcon, EyeIcon, PencilSquareIcon } from '@heroicons/react/24/outline';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(n);
const rp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
const tgl = (d) => d ? new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

export default function JettyPointsIndex({ jetties, can }) {
    const [deleteItem, setDeleteItem] = useState(null);

    const handleDelete = () => {
        if (deleteItem) router.delete(route('jetty-points.destroy', deleteItem.id), { onSuccess: () => setDeleteItem(null) });
    };

    return (
        <>
            <Head title="Titik Dermaga" />
            <AppLayout title="Titik Dermaga">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <div>
                        <p className="text-slate-300 text-sm font-medium">Daftar Titik Dermaga</p>
                        <p className="text-slate-500 text-xs mt-0.5">
                            <span className="inline-block w-2 h-2 rounded-full bg-blue-500 mr-1.5 align-middle" />
                            {jetties.total} dermaga — tampil 🚢 biru di peta
                        </p>
                    </div>
                    {can?.create && (
                        <Link href={route('jetty-points.create')} className="btn-primary">
                            <PlusIcon className="w-4 h-4" /> Tambah Dermaga
                        </Link>
                    )}
                </div>

                <div className="card !p-0 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px]">
                            <thead>
                                <tr className="bg-slate-900/40 border-b border-slate-700/70">
                                    <th className="table-header">Nama Dermaga</th>
                                    <th className="table-header">Lokasi</th>
                                    <th className="table-header text-right">Kapasitas Sandar</th>
                                    <th className="table-header text-right">Biaya incl. PPN 11%</th>
                                    <th className="table-header">Update Terakhir</th>
                                    <th className="table-header">Status</th>
                                    <th className="table-header text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {jetties.data.length === 0 && (
                                    <tr><td colSpan={7} className="px-4 py-12 text-center text-slate-500 text-sm">Belum ada dermaga. Klik “Tambah Dermaga” untuk memulai.</td></tr>
                                )}
                                {jetties.data.map((j) => (
                                    <tr key={j.id} className="border-b border-slate-800/70 last:border-0 hover:bg-slate-700/20 transition-colors">
                                        <td className="table-cell font-medium text-white">
                                            <Link href={route('jetty-points.show', j.id)} className="inline-flex items-center gap-2 hover:text-blue-300 transition-colors">
                                                <span className="text-blue-400">🚢</span>{j.name}
                                            </Link>
                                            {j.operator && <p className="text-slate-500 text-xs font-normal ml-6">{j.operator}</p>}
                                        </td>
                                        <td className="table-cell text-slate-400">
                                            <span className="inline-flex items-center gap-1.5"><MapPinIcon className="w-4 h-4 text-slate-500 shrink-0" />{j.city}, {j.province}</span>
                                        </td>
                                        <td className="table-cell text-right font-semibold tabular text-slate-200">
                                            {j.capacity ? <>{fmt(j.capacity)} <span className="text-slate-500 font-normal">{j.unit}</span></> : '—'}
                                        </td>
                                        <td className="table-cell text-right tabular text-slate-200">
                                            {j.price > 0 ? <>{rp(j.price_incl_ppn)}<span className="text-slate-500 text-xs">/{j.unit}</span></> : '—'}
                                        </td>
                                        <td className="table-cell text-slate-400 text-xs whitespace-nowrap">{tgl(j.updated_at)}</td>
                                        <td className="table-cell">
                                            <span className={`badge ${j.status === 'active' ? 'badge-green' : 'badge-slate'}`}>{j.status === 'active' ? 'Aktif' : 'Nonaktif'}</span>
                                        </td>
                                        <td className="table-cell">
                                            <div className="flex items-center justify-end gap-1">
                                                {can?.view && <Link href={route('jetty-points.show', j.id)} className="p-1.5 rounded-md text-slate-400 hover:text-blue-300 hover:bg-slate-700/50 transition-colors" title="Lihat"><EyeIcon className="w-4 h-4" /></Link>}
                                                {can?.edit && <Link href={route('jetty-points.edit', j.id)} className="p-1.5 rounded-md text-slate-400 hover:text-amber-300 hover:bg-slate-700/50 transition-colors" title="Edit"><PencilSquareIcon className="w-4 h-4" /></Link>}
                                                {can?.delete && <button onClick={() => setDeleteItem(j)} className="p-1.5 rounded-md text-slate-400 hover:text-red-400 hover:bg-slate-700/50 transition-colors" title="Hapus"><TrashIcon className="w-4 h-4" /></button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {jetties.links && jetties.last_page > 1 && (
                    <div className="flex justify-center gap-1 mt-5">
                        {jetties.links.map((link, idx) => (
                            <Link key={idx} href={link.url || '#'}
                                className={`min-w-[34px] text-center px-3 py-1.5 text-sm rounded-lg transition-colors
                                    ${link.active ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'}
                                    ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}

                {deleteItem && (
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in" onClick={() => setDeleteItem(null)}>
                        <div className="card w-full max-w-sm" onClick={(e) => e.stopPropagation()}>
                            <div className="flex items-start gap-3">
                                <div className="w-10 h-10 rounded-full bg-red-500/15 ring-1 ring-inset ring-red-500/20 flex items-center justify-center shrink-0">
                                    <TrashIcon className="w-5 h-5 text-red-400" />
                                </div>
                                <div>
                                    <h2 className="text-white font-semibold">Hapus Dermaga?</h2>
                                    <p className="text-slate-400 text-sm mt-1"><span className="text-slate-200">{deleteItem.name}</span> akan dihapus permanen.</p>
                                </div>
                            </div>
                            <div className="flex gap-3 pt-5">
                                <button onClick={handleDelete} className="btn-danger flex-1">Ya, Hapus</button>
                                <button onClick={() => setDeleteItem(null)} className="btn-secondary flex-1">Batal</button>
                            </div>
                        </div>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
