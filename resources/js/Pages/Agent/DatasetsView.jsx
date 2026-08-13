import { useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ArrowUpTrayIcon, TrashIcon, TableCellsIcon } from '@heroicons/react/24/outline';
import { fmtTime } from './constants';

const fmtSize = (bytes) => (bytes > 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`);

/**
 * Berkas data yang boleh dibaca asisten. Nama berkasnyalah yang disebut di
 * dalam instruksi, jadi daftar ini sekaligus menjadi "kamus" bagi pengguna.
 */
export default function DatasetsView({ datasets, canUpload, canDelete }) {
    const input = useRef(null);
    const form  = useForm({ file: null });

    const upload = (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        form.transform(() => ({ file }));
        form.post(route('agent.datasets.store'), {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => { if (input.current) input.current.value = ''; },
        });
    };

    const remove = (name) => {
        if (!window.confirm(`Hapus berkas ${name}?`)) return;
        router.delete(route('agent.datasets.destroy', name), { preserveScroll: true });
    };

    return (
        <div className="space-y-5">
            <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Berkas Data</h3>
                <p className="text-xs text-warm-500 mt-1 max-w-3xl">
                    Unggah berkas CSV/TSV yang boleh dibaca asisten — data penjualan, piutang, stok, apa pun.
                    Sebut namanya di dalam instruksi, misalnya:{' '}
                    <span className="text-[rgba(0,0,0,0.8)]">“buat laporan penjualan dari berkas penjualan-2026-08.csv”</span>.
                    Bila nama berkasnya tidak disebut, asisten akan berhenti dan bertanya — ia tidak menebak sumber data.
                </p>
                <p className="text-[11px] text-warm-300 mt-2">
                    Berkas tersimpan di ruang kerja privat aplikasi (bukan folder publik) dan tidak dapat diunduh lewat URL tebakan.
                    Maksimal 5 MB per berkas.
                </p>

                {canUpload && (
                    <div className="mt-3 pt-3 border-t border-black/5">
                        <input ref={input} type="file" accept=".csv,.tsv,.txt" onChange={upload} className="hidden" />
                        <button type="button" disabled={form.processing} onClick={() => input.current?.click()}
                            className="flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg bg-notion-blue text-white hover:opacity-90 disabled:opacity-50">
                            <ArrowUpTrayIcon className="w-4 h-4" />
                            {form.processing ? 'Mengunggah…' : 'Unggah berkas data'}
                        </button>
                        {form.errors.file && <p className="text-xs text-red-600 mt-1.5">{form.errors.file}</p>}
                    </div>
                )}
            </div>

            <div className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[520px]">
                        <thead>
                            <tr className="bg-warm-white border-b border-black/10 text-xs text-warm-500">
                                <th className="p-3 font-medium">Berkas</th>
                                <th className="p-3 font-medium text-right">Ukuran</th>
                                <th className="p-3 font-medium">Diperbarui</th>
                                <th className="p-3 font-medium text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-black/5 text-xs">
                            {datasets.length === 0 && (
                                <tr><td colSpan={4} className="p-6 text-center text-warm-500">
                                    Belum ada berkas data. Unggah satu untuk mulai menugaskan pekerjaan berbasis data.
                                </td></tr>
                            )}
                            {datasets.map((file) => (
                                <tr key={file.name} className="hover:bg-warm-white transition-colors">
                                    <td className="p-3">
                                        <span className="flex items-center gap-2 font-medium text-[rgba(0,0,0,0.9)]">
                                            <TableCellsIcon className="w-4 h-4 text-warm-300" /> {file.name}
                                        </span>
                                    </td>
                                    <td className="p-3 text-right text-warm-500">{fmtSize(file.bytes)}</td>
                                    <td className="p-3 text-warm-500">{fmtTime(file.modified_at)}</td>
                                    <td className="p-3 text-right">
                                        {canDelete && (
                                            <button type="button" onClick={() => remove(file.name)}
                                                className="inline-flex items-center gap-1 text-red-600 hover:underline">
                                                <TrashIcon className="w-3.5 h-3.5" /> Hapus
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
