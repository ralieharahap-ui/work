import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => (Number(n) ? new Intl.NumberFormat('id-ID').format(Math.round(n)) : '—');
const tgl = (s) => new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

export default function DocumentsIndex({ types, documents, filter }) {
    const [type, setType] = useState(filter.type ?? '');
    const [year, setYear] = useState(filter.year);

    const entries = Object.entries(types);
    const reload = (next = {}) =>
        router.get(route('documents.index'), { type: type || undefined, year, ...next }, { preserveState: true });

    return (
        <>
            <Head title="Dokumen Template" />
            <AppLayout title="Dokumen Template">
                <div className="mb-6">
                    <h2 className="section-title">Buat Dokumen Baru</h2>
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        {entries.map(([key, t]) => (
                            t.active ? (
                                <Link key={key} href={route('documents.create', { type: key })}
                                    className="card card-hover flex flex-col items-center justify-center text-center gap-2 py-5">
                                    <span className="text-3xl">{t.icon}</span>
                                    <span className="text-sm font-medium text-white">{t.label}</span>
                                    <span className="badge badge-blue text-[10px]">{t.prefix}</span>
                                </Link>
                            ) : (
                                <div key={key}
                                    className="card flex flex-col items-center justify-center text-center gap-2 py-5 opacity-50 cursor-not-allowed">
                                    <span className="text-3xl grayscale">{t.icon}</span>
                                    <span className="text-sm font-medium text-slate-300">{t.label}</span>
                                    <span className="badge badge-slate text-[10px]">Segera</span>
                                </div>
                            )
                        ))}
                    </div>
                </div>

                <h2 className="section-title">Riwayat Dokumen</h2>
                <div className="flex flex-wrap items-end gap-3 mb-4">
                    <div className="min-w-[200px]">
                        <label className="label">Jenis</label>
                        <select className="input" value={type}
                            onChange={(e) => { setType(e.target.value); reload({ type: e.target.value || undefined }); }}>
                            <option value="">Semua jenis</option>
                            {entries.filter(([, t]) => t.active).map(([key, t]) => (
                                <option key={key} value={key}>{t.label}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="label">Tahun</label>
                        <input type="number" className="input w-28" value={year}
                            onChange={(e) => setYear(e.target.value)} onBlur={() => reload()} />
                    </div>
                </div>

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Nomor</th>
                                <th className="table-header">Jenis</th>
                                <th className="table-header">Tanggal</th>
                                <th className="table-header">Pihak / Tujuan</th>
                                <th className="table-header text-right">Nilai</th>
                                <th className="table-header">Dibuat oleh</th>
                                <th className="table-header"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {documents.length === 0 && (
                                <tr><td colSpan={7} className="table-cell text-center text-slate-400">Belum ada dokumen.</td></tr>
                            )}
                            {documents.map((d) => (
                                <tr key={d.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{d.number}</td>
                                    <td className="table-cell">{types[d.type]?.label ?? d.type}</td>
                                    <td className="table-cell whitespace-nowrap">{tgl(d.doc_date)}</td>
                                    <td className="table-cell">{d.party}</td>
                                    <td className="table-cell text-right">{d.total != null ? fmt(d.total) : '—'}</td>
                                    <td className="table-cell text-slate-400">{d.user ?? '—'}</td>
                                    <td className="table-cell whitespace-nowrap">
                                        <Link href={route('documents.show', d.id)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Lihat</Link>
                                        <button onClick={() => router.delete(route('documents.destroy', d.id), { preserveScroll: true })}
                                            className="text-red-400 hover:text-red-300 text-xs">Hapus</button>
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
