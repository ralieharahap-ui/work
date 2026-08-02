import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    DocumentDuplicateIcon, TrashIcon, CheckBadgeIcon, ArrowTopRightOnSquareIcon,
    PrinterIcon, LockClosedIcon,
} from '@heroicons/react/24/outline';
import { fmtDate } from './constants';

const CATEGORY_BADGE = {
    'Berita Acara': 'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    'Kertas Kerja': 'bg-purple-50 text-purple-700',
    Laporan: 'bg-amber-50 text-amber-700',
    Checklist: 'bg-green-50 text-green-700',
    'Serah Terima': 'bg-orange-50 text-orange-700',
};

/**
 * Ikhtisar modul dokumen bukti: katalog template & kertas kerja yang tersedia,
 * plus seluruh dokumen yang sudah dibuat lintas task.
 */
export default function TemplatesView({ templates, tasks, placeholders, canManage, onOpenTask }) {
    const [busy, setBusy] = useState(false);

    // Dokumen dikumpulkan dari seluruh task agar terlihat sebagai satu arsip.
    const documents = useMemo(
        () => tasks.flatMap((task) => (task.evidence_documents ?? []).map((doc) => ({ ...doc, task })))
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at)),
        [tasks],
    );

    const duplicate = (template) => {
        setBusy(true);
        router.post(route('evidence-templates.duplicate', template.id), {}, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    const remove = (template) => {
        if (!window.confirm(`Hapus template "${template.name}"?`)) return;
        router.delete(route('evidence-templates.destroy', template.id), { preserveScroll: true });
    };

    const grouped = templates.reduce((acc, t) => {
        (acc[t.category] ||= []).push(t);
        return acc;
    }, {});

    return (
        <div className="space-y-5">
            <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Pemenuhan Dokumen Evidence</h3>
                <p className="text-xs text-warm-500 mt-1 max-w-3xl">
                    Setiap task hanya bisa ditutup bila buktinya lengkap. Pilih template di bawah dari dalam sebuah task,
                    sunting kertas kerjanya, cetak bila perlu, lalu tanda tangani — dokumen otomatis dibekukan menjadi
                    PDF dan siap dilampirkan sebagai syarat penyelesaian task.
                </p>
                <p className="text-xs text-warm-500 mt-2">
                    Penanda seperti <code className="bg-warm-100 px-1 rounded">{'{{task.title}}'}</code> atau{' '}
                    <code className="bg-warm-100 px-1 rounded">{'{{pic.name}}'}</code> terisi otomatis
                    ({Object.keys(placeholders || {}).length} penanda tersedia).
                </p>
            </div>

            {/* Katalog template */}
            {Object.entries(grouped).map(([category, items]) => (
                <div key={category}>
                    <p className="text-[11px] font-bold uppercase tracking-wider text-warm-500 mb-2">{category}</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        {items.map((t) => (
                            <div key={t.id} className="p-4 rounded-xl border border-black/10 bg-white shadow-notion flex flex-col">
                                <div className="flex items-start justify-between gap-2">
                                    <span className={`text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded ${CATEGORY_BADGE[t.category] || 'bg-warm-100 text-warm-500'}`}>
                                        {t.category}
                                    </span>
                                    {t.is_system && (
                                        <span className="flex items-center gap-1 text-[10px] text-warm-300" title="Template bawaan sistem — duplikasi untuk menyesuaikan">
                                            <LockClosedIcon className="w-3 h-3" /> Bawaan
                                        </span>
                                    )}
                                </div>
                                <p className="font-semibold text-sm text-[rgba(0,0,0,0.9)] mt-2">{t.name}</p>
                                <p className="text-xs text-warm-500 mt-1 flex-1">{t.description}</p>
                                <p className="text-[10px] text-warm-300 mt-2">
                                    {t.orientation === 'landscape' ? 'Kertas melintang' : 'Kertas tegak'}
                                    {t.fields?.length ? ` · ${t.fields.length} kolom isian` : ''}
                                </p>

                                {canManage && (
                                    <div className="flex items-center gap-2 mt-3 pt-3 border-t border-black/5">
                                        <button
                                            type="button"
                                            disabled={busy}
                                            onClick={() => duplicate(t)}
                                            className="flex items-center gap-1 text-xs text-notion-blue hover:underline disabled:opacity-50"
                                        >
                                            <DocumentDuplicateIcon className="w-3.5 h-3.5" /> Duplikasi
                                        </button>
                                        {!t.is_system && (
                                            <button
                                                type="button"
                                                onClick={() => remove(t)}
                                                className="flex items-center gap-1 text-xs text-red-600 hover:underline ml-auto"
                                            >
                                                <TrashIcon className="w-3.5 h-3.5" /> Hapus
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ))}

            {/* Arsip dokumen */}
            <div className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                <div className="p-4 border-b border-black/10 bg-warm-white">
                    <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Arsip Dokumen Bukti</h3>
                    <p className="text-xs text-warm-500 mt-1">Seluruh dokumen yang sudah dibuat pada task mana pun.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[760px]">
                        <thead>
                            <tr className="bg-white border-b border-black/10 text-xs text-warm-500">
                                <th className="p-3 font-medium">Dokumen</th>
                                <th className="p-3 font-medium">Task</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Penanda Tangan</th>
                                <th className="p-3 font-medium text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-black/5 text-xs">
                            {documents.length === 0 && (
                                <tr><td colSpan={5} className="p-6 text-center text-warm-500">Belum ada dokumen bukti yang dibuat.</td></tr>
                            )}
                            {documents.map((doc) => (
                                <tr key={doc.id} className="hover:bg-warm-white transition-colors">
                                    <td className="p-3">
                                        <p className="font-medium text-[rgba(0,0,0,0.9)]">{doc.title}</p>
                                        <p className="text-warm-300 mt-0.5">{doc.number || '—'} · {fmtDate(doc.created_at)}</p>
                                    </td>
                                    <td className="p-3">
                                        <button type="button" onClick={() => onOpenTask(doc.task)} className="text-notion-blue hover:underline text-left">
                                            {doc.task.title}
                                        </button>
                                    </td>
                                    <td className="p-3">
                                        {doc.is_signed ? (
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-50 text-green-700 font-semibold">
                                                <CheckBadgeIcon className="w-3 h-3" /> Ditandatangani
                                            </span>
                                        ) : (
                                            <span className="inline-flex px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-semibold">Draf</span>
                                        )}
                                    </td>
                                    <td className="p-3 text-warm-500">
                                        {doc.signer_name || '—'}
                                        {doc.signed_at && <span className="block text-warm-300">{fmtDate(doc.signed_at)}</span>}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex items-center justify-end gap-3">
                                            <a
                                                href={route('evidence-documents.print', doc.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="flex items-center gap-1 text-warm-500 hover:text-notion-blue"
                                            >
                                                <PrinterIcon className="w-4 h-4" /> Cetak
                                            </a>
                                            {doc.is_signed && (
                                                <a
                                                    href={route('evidence-documents.download', doc.id)}
                                                    className="flex items-center gap-1 text-green-700 hover:underline"
                                                >
                                                    <ArrowTopRightOnSquareIcon className="w-4 h-4" /> PDF
                                                </a>
                                            )}
                                        </div>
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
