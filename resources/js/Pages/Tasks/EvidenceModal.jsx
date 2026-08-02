import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    XMarkIcon, DocumentPlusIcon, PrinterIcon, PencilSquareIcon, TrashIcon,
    ArrowDownTrayIcon, CheckBadgeIcon, DocumentTextIcon, PaperClipIcon,
} from '@heroicons/react/24/outline';
import RichTextEditor from '@/Components/RichTextEditor';
import SignatureModal from './SignatureModal';
import { fmtDate } from './constants';

const CATEGORY_BADGE = {
    'Berita Acara': 'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    'Kertas Kerja': 'bg-purple-50 text-purple-700',
    Laporan: 'bg-amber-50 text-amber-700',
    Checklist: 'bg-green-50 text-green-700',
    'Serah Terima': 'bg-orange-50 text-orange-700',
};

/**
 * Ruang kerja dokumen bukti sebuah task:
 * pilih template → sunting kertas kerjanya → cetak → tanda tangani (jadi PDF)
 * → jadikan syarat penutupan task.
 */
export default function EvidenceModal({ task, templates, placeholders, canEdit, currentUserId, onClose, onUseAsEvidence }) {
    const documents = task.evidence_documents ?? [];

    const [activeId, setActiveId] = useState(documents[0]?.id ?? null);
    const [creating, setCreating] = useState(documents.length === 0);
    const [signing, setSigning] = useState(false);
    const [saving, setSaving] = useState(false);
    const [draft, setDraft] = useState(null);   // salinan yang sedang disunting

    const active = useMemo(() => documents.find((d) => d.id === activeId) ?? null, [documents, activeId]);
    const editing = draft?.id === active?.id ? draft : null;
    const isPic = task.pic_id === currentUserId;
    const newestId = documents[0]?.id ?? null;

    // Daftar dokumen datang ulang dari server tiap kali data disegarkan. Bila
    // pilihan saat ini kosong (mis. baru selesai membuat dokumen) atau dokumennya
    // sudah tidak ada (terhapus), langsung tampilkan yang terbaru.
    useEffect(() => {
        if (!creating && !active && newestId) {
            setActiveId(newestId);
        }
    }, [creating, active, newestId]);

    const startEditing = () => setDraft({
        id: active.id,
        title: active.title || '',
        number: active.number || '',
        orientation: active.orientation || 'portrait',
        content_html: active.content_html || '',
        data: { ...(active.data || {}) },
    });

    const createDocument = (templateId) => {
        setSaving(true);
        router.post(route('evidence-documents.store', task.id), { template_id: templateId }, {
            preserveScroll: true,
            onSuccess: () => { setCreating(false); setActiveId(null); },
            onFinish: () => setSaving(false),
        });
    };

    const saveDocument = () => {
        setSaving(true);
        router.put(route('evidence-documents.update', editing.id), editing, {
            preserveScroll: true,
            onSuccess: () => setDraft(null),
            onFinish: () => setSaving(false),
        });
    };

    const removeDocument = (doc) => {
        if (!window.confirm(`Hapus dokumen "${doc.title}"?`)) return;
        router.delete(route('evidence-documents.destroy', doc.id), {
            preserveScroll: true,
            onSuccess: () => { setActiveId(null); setDraft(null); },
        });
    };

    const templateFields = active?.template_id
        ? (templates.find((t) => t.id === active.template_id)?.fields ?? [])
        : [];

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[65] p-2 sm:p-4">
            <div className="bg-white rounded-xl shadow-notion-deep w-full max-w-6xl h-[94vh] overflow-hidden flex flex-col">

                <div className="px-4 sm:px-6 py-4 border-b border-black/10 flex justify-between items-center bg-warm-white shrink-0">
                    <div className="min-w-0">
                        <h2 className="text-lg font-bold text-[rgba(0,0,0,0.95)] flex items-center gap-2">
                            <DocumentTextIcon className="w-5 h-5 text-notion-blue shrink-0" />
                            <span className="truncate">Dokumen Bukti (Evidence)</span>
                        </h2>
                        <p className="text-xs text-warm-500 mt-0.5 truncate">
                            Task: {task.title} · PIC: {task.pic?.name || 'belum ditentukan'}
                        </p>
                    </div>
                    <button onClick={onClose} className="text-warm-300 hover:text-black/70 shrink-0 ml-3">
                        <XMarkIcon className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 flex flex-col lg:flex-row min-h-0">

                    {/* Daftar dokumen milik task ini */}
                    <aside className="lg:w-72 shrink-0 border-b lg:border-b-0 lg:border-r border-black/10 bg-warm-white flex flex-col max-h-52 lg:max-h-none">
                        <div className="p-3 border-b border-black/5">
                            <button
                                type="button"
                                disabled={!canEdit}
                                onClick={() => { setCreating(true); setActiveId(null); setDraft(null); }}
                                className="w-full flex items-center justify-center gap-2 bg-notion-blue hover:bg-notion-blue-active text-white text-sm font-medium px-3 py-2 rounded-md disabled:opacity-50 transition-colors"
                            >
                                <DocumentPlusIcon className="w-4 h-4" /> Dokumen Baru
                            </button>
                        </div>
                        <div className="flex-1 overflow-y-auto p-2 space-y-1.5">
                            {documents.length === 0 && (
                                <p className="text-xs text-warm-500 p-3 text-center">Belum ada dokumen bukti.</p>
                            )}
                            {documents.map((doc) => (
                                <button
                                    key={doc.id}
                                    type="button"
                                    onClick={() => { setActiveId(doc.id); setCreating(false); setDraft(null); }}
                                    className={`w-full text-left p-2.5 rounded-lg border transition-colors ${
                                        activeId === doc.id && !creating
                                            ? 'bg-white border-notion-blue shadow-sm'
                                            : 'bg-white/60 border-black/5 hover:bg-white'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <span className="text-xs font-semibold text-[rgba(0,0,0,0.85)] line-clamp-2">{doc.title}</span>
                                        {doc.is_signed ? (
                                            <CheckBadgeIcon className="w-4 h-4 text-green-600 shrink-0" title="Sudah ditandatangani" />
                                        ) : (
                                            <span className="text-[9px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded shrink-0">Draf</span>
                                        )}
                                    </div>
                                    <p className="text-[10px] text-warm-500 mt-1">{doc.number || '—'} · {fmtDate(doc.created_at)}</p>
                                </button>
                            ))}
                        </div>
                    </aside>

                    {/* Panel utama */}
                    <section className="flex-1 overflow-y-auto min-w-0">
                        {creating && (
                            <TemplatePicker
                                templates={templates}
                                disabled={!canEdit || saving}
                                onPick={createDocument}
                                onCancel={documents.length > 0 ? () => setCreating(false) : null}
                            />
                        )}

                        {!creating && !active && (
                            <div className="h-full flex items-center justify-center p-10 text-center">
                                <p className="text-sm text-warm-500">Pilih dokumen di samping, atau buat dokumen baru dari template.</p>
                            </div>
                        )}

                        {!creating && active && (
                            <div className="p-4 sm:p-6 space-y-4">

                                {/* Baris aksi */}
                                <div className="flex flex-wrap items-center gap-2">
                                    <a
                                        href={route('evidence-documents.print', active.id)}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-white transition-colors"
                                    >
                                        <PrinterIcon className="w-4 h-4" /> Cetak / Pratinjau
                                    </a>

                                    {active.is_signed ? (
                                        <>
                                            <a
                                                href={route('evidence-documents.download', active.id)}
                                                className="flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md bg-green-50 border border-green-200 text-green-700 hover:bg-green-100 transition-colors"
                                            >
                                                <ArrowDownTrayIcon className="w-4 h-4" /> Unduh PDF
                                            </a>
                                            {task.status !== 'Done' && canEdit && (
                                                <button
                                                    type="button"
                                                    onClick={() => onUseAsEvidence(active)}
                                                    className="flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md bg-notion-blue text-white hover:bg-notion-blue-active transition-colors"
                                                >
                                                    <PaperClipIcon className="w-4 h-4" /> Jadikan Bukti &amp; Tutup Task
                                                </button>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            {canEdit && !editing && (
                                                <button
                                                    type="button"
                                                    onClick={startEditing}
                                                    className="flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-white transition-colors"
                                                >
                                                    <PencilSquareIcon className="w-4 h-4" /> Sunting
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => setSigning(true)}
                                                disabled={!!editing}
                                                className="flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
                                            >
                                                <CheckBadgeIcon className="w-4 h-4" /> Tanda Tangani (jadi PDF)
                                            </button>
                                        </>
                                    )}

                                    {canEdit && (
                                        <button
                                            type="button"
                                            onClick={() => removeDocument(active)}
                                            className="ml-auto flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md text-red-600 hover:bg-red-50 transition-colors"
                                        >
                                            <TrashIcon className="w-4 h-4" /> Hapus
                                        </button>
                                    )}
                                </div>

                                {active.is_signed && (
                                    <div className="bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800">
                                        Ditandatangani oleh <strong>{active.signer_name}</strong>
                                        {active.signer_position ? ` (${active.signer_position})` : ''} pada {fmtDate(active.signed_at)}.
                                        Dokumen dibekukan menjadi PDF: <strong>{active.pdf_original_name}</strong>.
                                    </div>
                                )}

                                {/* Judul & nomor */}
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div className="sm:col-span-2">
                                        <label className="block text-xs font-medium text-[rgba(0,0,0,0.6)] mb-1">Judul Dokumen</label>
                                        <input
                                            type="text"
                                            value={editing ? editing.title : active.title}
                                            disabled={!editing}
                                            onChange={(e) => setDraft({ ...editing, title: e.target.value })}
                                            className="w-full p-2 border border-black/10 rounded-md text-sm text-[rgba(0,0,0,0.9)] outline-none focus:border-notion-blue disabled:bg-warm-white"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-[rgba(0,0,0,0.6)] mb-1">Nomor Dokumen</label>
                                        <input
                                            type="text"
                                            value={editing ? editing.number : (active.number || '')}
                                            disabled={!editing}
                                            onChange={(e) => setDraft({ ...editing, number: e.target.value })}
                                            className="w-full p-2 border border-black/10 rounded-md text-sm text-[rgba(0,0,0,0.9)] outline-none focus:border-notion-blue disabled:bg-warm-white"
                                        />
                                    </div>
                                </div>

                                {/* Kolom isian tambahan dari template */}
                                {templateFields.length > 0 && (
                                    <div className="bg-warm-white border border-black/10 rounded-lg p-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {templateFields.map((f) => (
                                            <div key={f.key}>
                                                <label className="block text-xs font-medium text-[rgba(0,0,0,0.6)] mb-1">{f.label}</label>
                                                <input
                                                    type={f.type === 'date' ? 'date' : 'text'}
                                                    placeholder={f.placeholder || ''}
                                                    disabled={!editing}
                                                    value={(editing ? editing.data : (active.data || {}))?.[f.key] ?? ''}
                                                    onChange={(e) => setDraft({ ...editing, data: { ...editing.data, [f.key]: e.target.value } })}
                                                    className="w-full p-2 border border-black/10 rounded-md text-sm text-[rgba(0,0,0,0.9)] outline-none focus:border-notion-blue disabled:bg-white"
                                                />
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* Isi dokumen / kertas kerja */}
                                {editing ? (
                                    <>
                                        <RichTextEditor
                                            value={editing.content_html}
                                            onChange={(html) => setDraft((d) => ({ ...d, content_html: html }))}
                                        />
                                        <div className="flex flex-wrap items-center gap-2">
                                            <label className="text-xs text-warm-500 flex items-center gap-1.5">
                                                Orientasi kertas:
                                                <select
                                                    value={editing.orientation}
                                                    onChange={(e) => setDraft({ ...editing, orientation: e.target.value })}
                                                    className="border border-black/10 rounded px-2 py-1 text-xs outline-none focus:border-notion-blue"
                                                >
                                                    <option value="portrait">Tegak (Portrait)</option>
                                                    <option value="landscape">Melintang (Landscape)</option>
                                                </select>
                                            </label>
                                            <div className="ml-auto flex gap-2">
                                                <button type="button" onClick={() => setDraft(null)} className="px-4 py-2 text-sm text-[rgba(0,0,0,0.8)] border border-black/10 rounded-md hover:bg-warm-white">
                                                    Batal
                                                </button>
                                                <button type="button" onClick={saveDocument} disabled={saving} className="px-4 py-2 text-sm text-white bg-notion-blue rounded-md hover:bg-notion-blue-active disabled:opacity-50">
                                                    {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                                                </button>
                                            </div>
                                        </div>
                                        <PlaceholderHint placeholders={placeholders} />
                                    </>
                                ) : (
                                    <div
                                        className="evidence-preview border border-black/10 rounded-lg p-5 bg-white text-sm max-h-[46vh] overflow-y-auto"
                                        dangerouslySetInnerHTML={{ __html: active.content_html }}
                                    />
                                )}
                            </div>
                        )}
                    </section>
                </div>
            </div>

            {signing && active && (
                <SignatureModal
                    document={active}
                    defaultName={isPic ? (task.pic?.name ?? '') : ''}
                    onClose={() => setSigning(false)}
                />
            )}
        </div>
    );
}

function TemplatePicker({ templates, onPick, onCancel, disabled }) {
    const grouped = templates.reduce((acc, t) => {
        (acc[t.category] ||= []).push(t);
        return acc;
    }, {});

    return (
        <div className="p-4 sm:p-6">
            <div className="flex items-start justify-between gap-3 mb-4">
                <div>
                    <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Pilih Template Dokumen</h3>
                    <p className="text-xs text-warm-500 mt-0.5">
                        Data task otomatis terisi ke dalam dokumen. Setelah dibuat, isinya masih bisa disunting bebas.
                    </p>
                </div>
                {onCancel && (
                    <button type="button" onClick={onCancel} className="text-xs text-warm-500 hover:text-black/70 shrink-0">Batal</button>
                )}
            </div>

            {Object.entries(grouped).map(([category, items]) => (
                <div key={category} className="mb-5">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-warm-500 mb-2">{category}</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        {items.map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                disabled={disabled}
                                onClick={() => onPick(t.id)}
                                className="text-left p-3.5 rounded-xl border border-black/10 bg-white hover:border-notion-blue hover:shadow-notion transition-all disabled:opacity-50"
                            >
                                <span className={`inline-block text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded ${CATEGORY_BADGE[t.category] || 'bg-warm-100 text-warm-500'}`}>
                                    {t.category}
                                </span>
                                <p className="font-semibold text-sm text-[rgba(0,0,0,0.9)] mt-2">{t.name}</p>
                                <p className="text-xs text-warm-500 mt-1 line-clamp-3">{t.description}</p>
                                {t.orientation === 'landscape' && (
                                    <p className="text-[10px] text-warm-300 mt-1.5">Kertas melintang (landscape)</p>
                                )}
                            </button>
                        ))}
                    </div>
                </div>
            ))}

            <button
                type="button"
                disabled={disabled}
                onClick={() => onPick(null)}
                className="w-full p-3.5 rounded-xl border-2 border-dashed border-black/15 text-sm text-warm-500 hover:border-notion-blue hover:text-notion-blue transition-colors disabled:opacity-50"
            >
                + Mulai dari dokumen kosong
            </button>
        </div>
    );
}

function PlaceholderHint({ placeholders }) {
    const [open, setOpen] = useState(false);
    const entries = Object.entries(placeholders || {});

    if (entries.length === 0) return null;

    return (
        <div className="border border-black/10 rounded-lg bg-warm-white">
            <button type="button" onClick={() => setOpen((v) => !v)} className="w-full text-left px-3 py-2 text-xs font-semibold text-[rgba(0,0,0,0.7)]">
                {open ? '▾' : '▸'} Penanda otomatis yang tersedia ({entries.length})
            </button>
            {open && (
                <div className="px-3 pb-3 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                    {entries.map(([token, label]) => (
                        <p key={token} className="text-[11px] text-warm-500">
                            <code className="text-[rgba(0,0,0,0.75)] bg-white px-1 rounded border border-black/5">{token}</code> — {label}
                        </p>
                    ))}
                    <p className="text-[11px] text-warm-300 sm:col-span-2 mt-1">
                        Penanda hanya diisi saat dokumen dibuat dari template; mengetiknya di sini tidak akan diganti otomatis.
                    </p>
                </div>
            )}
        </div>
    );
}
