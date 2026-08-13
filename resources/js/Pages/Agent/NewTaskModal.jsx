import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

const CONTOH = [
    'Buat laporan penjualan bulan ini dari berkas penjualan-2026-08.csv',
    'Bandingkan piutang-sistem.csv dengan piutang-bank.csv lalu catat selisihnya',
    'Rangkum tugas yang sudah lewat tenggat beserta rencana tindak lanjutnya',
    'Susun draf email penawaran untuk klien lalu kirim setelah saya setujui',
];

/**
 * Formulir penugasan. Instruksi ditulis bahasa biasa; konteks tambahan
 * (berkas sumber, penerima, dsb.) bersifat opsional dan membantu agent
 * bekerja tanpa menebak.
 */
export default function NewTaskModal({ onClose }) {
    const [context, setContext] = useState([{ key: '', value: '' }]);

    const form = useForm({
        objective: '',
        priority: 'Medium',
        deadline: '',
        expected_output: '',
        context: {},
    });

    const submit = (event) => {
        event.preventDefault();

        const map = {};
        context.forEach(({ key, value }) => {
            if (!key.trim()) return;
            // Nilai yang dipisah koma diperlakukan sebagai daftar (mis. penerima email).
            map[key.trim()] = value.includes(',')
                ? value.split(',').map((part) => part.trim()).filter(Boolean)
                : value;
        });

        // useForm React mengembalikan undefined dari transform(), jadi
        // pemanggilannya tidak boleh dirantai dengan post().
        form.transform((data) => ({ ...data, context: map }));
        form.post(route('agent.tasks.store'), { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl my-8">
                <div className="flex items-center justify-between p-4 border-b border-black/10">
                    <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Tugaskan pekerjaan baru</h3>
                    <button type="button" onClick={onClose} className="text-warm-500 hover:text-[rgba(0,0,0,0.8)]" aria-label="Tutup">
                        <XMarkIcon className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={submit} className="p-4 space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-warm-500 mb-1">Apa yang perlu dikerjakan?</label>
                        <textarea
                            rows={3}
                            value={form.data.objective}
                            onChange={(event) => form.setData('objective', event.target.value)}
                            placeholder="Tulis dengan bahasa biasa, mis. “Buat laporan penjualan bulan ini dari berkas penjualan-2026-08.csv”"
                            className="w-full text-sm rounded-lg border-black/10 text-[rgba(0,0,0,0.8)] focus:border-notion-blue focus:ring-notion-blue"
                        />
                        {form.errors.objective && <p className="text-xs text-red-600 mt-1">{form.errors.objective}</p>}

                        <div className="flex flex-wrap gap-1.5 mt-2">
                            {CONTOH.map((contoh) => (
                                <button key={contoh} type="button" onClick={() => form.setData('objective', contoh)}
                                    className="text-[11px] px-2 py-1 rounded-full bg-warm-100 text-warm-500 hover:bg-warm-200">
                                    {contoh}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-warm-500 mb-1">Prioritas</label>
                            <select value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)}
                                className="w-full text-sm rounded-lg border-black/10 text-[rgba(0,0,0,0.8)] focus:border-notion-blue focus:ring-notion-blue">
                                {['Low', 'Medium', 'High', 'Urgent'].map((value) => <option key={value}>{value}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-warm-500 mb-1">Tenggat (opsional)</label>
                            <input type="date" value={form.data.deadline}
                                onChange={(event) => form.setData('deadline', event.target.value)}
                                className="w-full text-sm rounded-lg border-black/10 text-[rgba(0,0,0,0.8)] focus:border-notion-blue focus:ring-notion-blue" />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-warm-500 mb-1">Bentuk hasil yang diharapkan (opsional)</label>
                        <input type="text" value={form.data.expected_output}
                            onChange={(event) => form.setData('expected_output', event.target.value)}
                            placeholder="mis. dokumen laporan berisi total per produk"
                            className="w-full text-sm rounded-lg border-black/10 text-[rgba(0,0,0,0.8)] focus:border-notion-blue focus:ring-notion-blue" />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-warm-500 mb-1">
                            Konteks tambahan (opsional) — mis. <code className="bg-warm-100 px-1 rounded">dataset</code>,
                            <code className="bg-warm-100 px-1 rounded ml-1">email_to</code>,
                            <code className="bg-warm-100 px-1 rounded ml-1">group_by</code>
                        </label>

                        {context.map((row, index) => (
                            <div key={index} className="flex gap-2 mb-1.5">
                                <input type="text" value={row.key} placeholder="kunci"
                                    onChange={(event) => setContext(context.map((item, i) => i === index ? { ...item, key: event.target.value } : item))}
                                    className="w-1/3 text-xs rounded-lg border-black/10 text-[rgba(0,0,0,0.8)] focus:border-notion-blue focus:ring-notion-blue" />
                                <input type="text" value={row.value} placeholder="nilai"
                                    onChange={(event) => setContext(context.map((item, i) => i === index ? { ...item, value: event.target.value } : item))}
                                    className="flex-1 text-xs rounded-lg border-black/10 text-[rgba(0,0,0,0.8)] focus:border-notion-blue focus:ring-notion-blue" />
                            </div>
                        ))}

                        <button type="button" onClick={() => setContext([...context, { key: '', value: '' }])}
                            className="text-xs text-notion-blue hover:underline">+ baris konteks</button>
                    </div>

                    <div className="flex justify-end gap-2 pt-2 border-t border-black/10">
                        <button type="button" onClick={onClose}
                            className="text-sm px-4 py-2 rounded-lg border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-50">
                            Batal
                        </button>
                        <button type="submit" disabled={form.processing}
                            className="text-sm px-4 py-2 rounded-lg bg-notion-blue text-white hover:opacity-90 disabled:opacity-50">
                            {form.processing ? 'Mengirim…' : 'Tugaskan'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
