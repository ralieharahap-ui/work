import { useState } from 'react';
import { router } from '@inertiajs/react';
import { XMarkIcon, DocumentArrowUpIcon, CheckCircleIcon, CheckBadgeIcon, DocumentTextIcon } from '@heroicons/react/24/outline';
import { fmtDate } from './constants';

/**
 * Menutup task selalu menuntut bukti. Buktinya boleh berupa berkas yang
 * diunggah manual, atau PDF dari dokumen evidence yang sudah ditandatangani
 * PIC di dalam aplikasi.
 */
export default function CloseTaskModal({ task, onClose, onOpenEvidence, preselectedDocumentId = null }) {
    const signedDocs = (task.evidence_documents ?? []).filter((d) => d.is_signed && d.pdf_url);

    const [mode, setMode] = useState(preselectedDocumentId || signedDocs.length > 0 ? 'document' : 'upload');
    const [documentId, setDocumentId] = useState(preselectedDocumentId ?? signedDocs[0]?.id ?? null);
    const [file, setFile] = useState(null);
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();

        if (mode === 'document' && !documentId) {
            setError('Pilih dokumen bukti yang sudah ditandatangani.');
            return;
        }
        if (mode === 'upload' && !file) {
            setError('Dokumen bukti (evidence) wajib diunggah untuk menutup task ini.');
            return;
        }

        setSubmitting(true);
        router.post(
            route('tasks.close', task.id),
            mode === 'document' ? { evidence_document_id: documentId } : { evidence: file },
            {
                forceFormData: mode === 'upload',
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errs) => {
                    setError(Object.values(errs)[0] || 'Gagal menutup task.');
                    setSubmitting(false);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const tab = (key, label, icon) => (
        <button
            type="button"
            onClick={() => { setMode(key); setError(''); }}
            className={`flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-md transition-colors ${
                mode === key ? 'bg-white text-notion-blue shadow-sm' : 'text-warm-500 hover:text-[rgba(0,0,0,0.8)]'
            }`}
        >
            {icon} {label}
        </button>
    );

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4">
            <div className="bg-white rounded-xl shadow-notion-deep w-full max-w-md max-h-[92vh] overflow-hidden flex flex-col">
                <div className="px-6 py-4 border-b border-black/10 flex justify-between items-center bg-warm-white shrink-0">
                    <h2 className="text-lg font-bold text-[rgba(0,0,0,0.95)] flex items-center gap-2">
                        <CheckCircleIcon className="w-5 h-5 text-green-600" /> Selesaikan Task
                    </h2>
                    <button onClick={onClose} className="text-warm-300 hover:text-black/70"><XMarkIcon className="w-5 h-5" /></button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4 overflow-y-auto">
                    <p className="text-sm text-warm-500">
                        Menutup <span className="font-semibold text-[rgba(0,0,0,0.85)]">{task.title}</span> mewajibkan
                        dokumen bukti (evidence) penyelesaian pekerjaan.
                    </p>

                    <div className="flex gap-1 p-1 bg-warm-100 rounded-lg">
                        {tab('document', 'Dokumen Bertanda Tangan', <CheckBadgeIcon className="w-4 h-4" />)}
                        {tab('upload', 'Unggah Berkas', <DocumentArrowUpIcon className="w-4 h-4" />)}
                    </div>

                    {mode === 'document' ? (
                        signedDocs.length > 0 ? (
                            <div className="space-y-2">
                                {signedDocs.map((doc) => (
                                    <label
                                        key={doc.id}
                                        className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                                            documentId === doc.id ? 'border-notion-blue bg-notion-blue-badge-bg/40' : 'border-black/10 hover:bg-warm-white'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="evidence_document"
                                            className="mt-1"
                                            checked={documentId === doc.id}
                                            onChange={() => { setDocumentId(doc.id); setError(''); }}
                                        />
                                        <span className="min-w-0">
                                            <span className="block text-sm font-medium text-[rgba(0,0,0,0.9)] truncate">{doc.title}</span>
                                            <span className="block text-[11px] text-warm-500 mt-0.5">
                                                {doc.number || '—'} · ditandatangani {doc.signer_name} pada {fmtDate(doc.signed_at)}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center border-2 border-dashed border-black/15 rounded-lg py-7 px-4 bg-warm-white">
                                <DocumentTextIcon className="w-8 h-8 text-warm-300 mx-auto mb-2" />
                                <p className="text-sm text-warm-500">Belum ada dokumen bukti yang ditandatangani.</p>
                                {onOpenEvidence && (
                                    <button
                                        type="button"
                                        onClick={() => { onClose(); onOpenEvidence(task); }}
                                        className="mt-3 text-xs font-semibold text-notion-blue hover:underline"
                                    >
                                        Buat dokumen bukti sekarang →
                                    </button>
                                )}
                            </div>
                        )
                    ) : (
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
                    )}

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
                            {submitting ? 'Memproses...' : 'Tandai Selesai'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
