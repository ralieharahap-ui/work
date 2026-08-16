import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const tgl = (s) => new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const STATUS_BADGE = {
    on_review: 'badge-amber', signed: 'badge-blue', released: 'badge-green', cancelled: 'badge-red',
};

export default function DocumentsLog({ documents, types, statuses, filter }) {
    const [type, setType] = useState(filter.type ?? '');
    const [status, setStatus] = useState(filter.status ?? '');
    const [year, setYear] = useState(filter.year);

    const reload = (next = {}) =>
        router.get(route('documents.log'), { type: type || undefined, status: status || undefined, year, ...next }, { preserveState: true });

    return (
        <>
            <Head title="Dokumentasi" />
            <AppLayout title="Dokumentasi — Rekaman Dokumen Terbit">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div className="min-w-[180px]">
                        <label className="label">Jenis</label>
                        <select className="input" value={type} onChange={(e) => { setType(e.target.value); reload({ type: e.target.value || undefined }); }}>
                            <option value="">Semua jenis</option>
                            {Object.entries(types).filter(([, t]) => t.active).map(([key, t]) => <option key={key} value={key}>{t.label}</option>)}
                        </select>
                    </div>
                    <div className="min-w-[160px]">
                        <label className="label">Status</label>
                        <select className="input" value={status} onChange={(e) => { setStatus(e.target.value); reload({ status: e.target.value || undefined }); }}>
                            <option value="">Semua status</option>
                            {Object.entries(statuses).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="label">Tahun</label>
                        <input type="number" className="input w-28" value={year} onChange={(e) => setYear(e.target.value)} onBlur={() => reload()} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Nomor Dokumen</th>
                                <th className="table-header">Tanggal</th>
                                <th className="table-header">Tipe Dokumen</th>
                                <th className="table-header">Perihal</th>
                                <th className="table-header">User Perilis</th>
                                <th className="table-header">Status</th>
                                <th className="table-header print:hidden"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {documents.length === 0 && (
                                <tr><td colSpan={7} className="table-cell text-center text-slate-400">Belum ada dokumen.</td></tr>
                            )}
                            {documents.map((d) => (
                                <tr key={d.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{d.number}</td>
                                    <td className="table-cell whitespace-nowrap">{tgl(d.doc_date)}</td>
                                    <td className="table-cell">{d.type_label}</td>
                                    <td className="table-cell">{d.perihal}</td>
                                    <td className="table-cell text-slate-300">{d.releaser || d.user}</td>
                                    <td className="table-cell">
                                        <span className={`badge ${STATUS_BADGE[d.status] || 'badge-slate'}`}>{statuses[d.status] || d.status}</span>
                                    </td>
                                    <td className="table-cell print:hidden">
                                        <Link href={route('documents.show', d.id)} className="text-blue-400 hover:text-blue-300 text-xs">Lihat</Link>
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
