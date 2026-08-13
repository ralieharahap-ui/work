import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { XMarkIcon, CheckBadgeIcon } from '@heroicons/react/24/outline';
import SignaturePad from '@/Components/SignaturePad';

/**
 * Pembubuhan tanda tangan PIC. Setelah dikirim, server membekukan dokumen dan
 * langsung menghasilkan berkas PDF yang siap dilampirkan ke task.
 */
export default function SignatureModal({ document: doc, defaultName, onClose }) {
    const padRef = useRef(null);
    const [hasStroke, setHasStroke] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        signer_name: defaultName || '',
        signer_position: '',
        signature_place: '',
    });

    const submit = (e) => {
        e.preventDefault();

        const signature = padRef.current?.toDataURL();

        if (!signature) {
            setError('Bubuhkan tanda tangan terlebih dahulu.');
            return;
        }
        if (!form.signer_name.trim()) {
            setError('Nama penanda tangan wajib diisi.');
            return;
        }

        setError('');
        setSubmitting(true);

        router.post(route('evidence-documents.sign', doc.id), { ...form, signature }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errs) => setError(Object.values(errs)[0] || 'Gagal menandatangani dokumen.'),
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-[80] p-3">
            <div className="bg-white rounded-xl shadow-notion-deep w-full max-w-lg max-h-[92vh] overflow-hidden flex flex-col">
                <div className="px-5 py-4 border-b border-black/10 flex justify-between items-center bg-warm-white shrink-0">
                    <h2 className="text-base font-bold text-[rgba(0,0,0,0.95)] flex items-center gap-2">
                        <CheckBadgeIcon className="w-5 h-5 text-green-600" /> Tanda Tangani Dokumen
                    </h2>
                    <button onClick={onClose} className="text-warm-300 hover:text-black/70"><XMarkIcon className="w-5 h-5" /></button>
                </div>

                <form onSubmit={submit} className="p-5 space-y-4 overflow-y-auto">
                    <p className="text-xs text-warm-500">
                        Dokumen <span className="font-semibold text-[rgba(0,0,0,0.85)]">{doc.title}</span> akan dibekukan
                        setelah ditandatangani dan otomatis diubah menjadi berkas PDF — isinya tidak dapat diubah lagi.
                    </p>

                    <div>
                        <label className="block text-xs font-medium text-[rgba(0,0,0,0.7)] mb-1">Nama Penanda Tangan *</label>
                        <input
                            required
                            type="text"
                            value={form.signer_name}
                            onChange={(e) => setForm({ ...form, signer_name: e.target.value })}
                            placeholder="Nama lengkap PIC"
                            className="w-full p-2 border border-black/10 rounded-md text-sm text-[rgba(0,0,0,0.9)] outline-none focus:border-notion-blue"
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-[rgba(0,0,0,0.7)] mb-1">Jabatan</label>
                            <input
                                type="text"
                                value={form.signer_position}
                                onChange={(e) => setForm({ ...form, signer_position: e.target.value })}
                                placeholder="Cth: Kepala Divisi Operasional"
                                className="w-full p-2 border border-black/10 rounded-md text-sm text-[rgba(0,0,0,0.9)] outline-none focus:border-notion-blue"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-[rgba(0,0,0,0.7)] mb-1">Tempat</label>
                            <input
                                type="text"
                                value={form.signature_place}
                                onChange={(e) => setForm({ ...form, signature_place: e.target.value })}
                                placeholder="Cth: Pekanbaru"
                                className="w-full p-2 border border-black/10 rounded-md text-sm text-[rgba(0,0,0,0.9)] outline-none focus:border-notion-blue"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-[rgba(0,0,0,0.7)] mb-1.5">Tanda Tangan *</label>
                        <SignaturePad ref={padRef} onChange={setHasStroke} />
                        <p className="text-[11px] text-warm-300 mt-1">
                            Gunakan jari pada layar sentuh, stylus, atau tahan tombol kiri mouse untuk menggores.
                        </p>
                    </div>

                    {error && <p className="text-red-600 text-xs">{error}</p>}

                    <div className="flex gap-2 pt-1">
                        <button type="button" onClick={onClose} className="flex-1 px-4 py-2 text-sm font-medium text-[rgba(0,0,0,0.8)] border border-black/10 rounded-md hover:bg-warm-white">
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={submitting || !hasStroke}
                            className="flex-1 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50"
                        >
                            {submitting ? 'Memproses PDF...' : 'Tanda Tangani & Buat PDF'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
