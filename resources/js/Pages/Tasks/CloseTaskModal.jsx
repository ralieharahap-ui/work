import { useState } from 'react';
import { router } from '@inertiajs/react';
import { XMarkIcon, DocumentArrowUpIcon, CheckCircleIcon } from '@heroicons/react/24/outline';

export default function CloseTaskModal({ task, onClose }) {
    const [file, setFile] = useState(null);
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!file) {
            setError('Dokumen bukti (evidence) wajib diunggah untuk menutup task ini.');
            return;
        }
        setSubmitting(true);
        router.post(
            route('tasks.close', task.id),
            { evidence: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errs) => {
                    setError(errs.evidence || 'Gagal mengunggah bukti.');
                    setSubmitting(false);
                },
                onFinish: () => setSubmitting(false),
            }
        );
    };

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4">
            <div className="bg-white rounded-xl shadow-notion-deep w-full max-w-md overflow-hidden flex flex-col">
                <div className="px-6 py-4 border-b border-black/10 flex justify-between items-center bg-warm-white">
                    <h2 className="text-lg font-bold text-[rgba(0,0,0,0.95)] flex items-center gap-2">
                        <CheckCircleIcon className="w-5 h-5 text-green-600" /> Selesaikan Task
                    </h2>
                    <button onClick={onClose} className="text-warm-300 hover:text-black/70"><XMarkIcon className="w-5 h-5" /></button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <p className="text-sm text-warm-500">
                        Menutup <span className="font-semibold text-[rgba(0,0,0,0.85)]">{task.title}</span> mewajibkan Anda melampirkan dokumen bukti (evidence) penyelesaian pekerjaan.
                    </p>

                    <label className="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-black/15 rounded-lg py-8 px-4 cursor-pointer hover:border-notion-blue transition-colors bg-warm-white">
                        <DocumentArrowUpIcon className="w-8 h-8 text-warm-300" />
                        <span className="text-sm text-warm-500 text-center">
                            {file ? file.name : 'Klik untuk pilih file (PDF, gambar, atau dokumen), maks 10MB'}
                        </span>
                        <input
                            type="file"
                            className="hidden"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                            onChange={(e) => { setFile(e.target.files[0] || null); setError(''); }}
                        />
                    </label>

                    {error && <p className="text-red-600 text-xs">{error}</p>}

                    <div className="flex gap-2 pt-2">
                        <button type="button" onClick={onClose} className="flex-1 px-4 py-2 text-sm font-medium text-[rgba(0,0,0,0.8)] bg-white border border-black/10 rounded-md hover:bg-warm-white">
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={submitting}
                            className="flex-1 px-4 py-2 text-sm font-medium text-white bg-notion-blue rounded-md hover:bg-notion-blue-active disabled:opacity-50"
                        >
                            {submitting ? 'Mengunggah...' : 'Tandai Selesai'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
