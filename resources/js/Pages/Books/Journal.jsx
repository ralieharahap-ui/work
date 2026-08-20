import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useMemo, useState } from 'react';

const fmt = (n) => (Number(n) ? new Intl.NumberFormat('id-ID').format(n) : '—');
const tgl = (s) => new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
const num = (v) => parseFloat(v) || 0;

const emptyLine = () => ({ account_id: '', aux_code: '', debit: '', credit: '', memo: '' });

const STATUS = {
    draft:    { label: 'Draft',      cls: 'badge-slate' },
    pending:  { label: 'Menunggu',   cls: 'badge-amber' },
    posted:   { label: 'Posted',     cls: 'badge-green' },
    rejected: { label: 'Ditolak',    cls: 'badge-red' },
};

export default function Journal({ accounts, entries, year, can_create, approve_level, threshold, aux_codes, recommendations = [] }) {
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);

    const { data, setData, post, processing, reset, transform } = useForm({
        entry_date: new Date().toISOString().slice(0, 10),
        description: '',
        lines: [emptyLine(), emptyLine()],
        documents: [],
        action: 'save',
    });

    const acctById = useMemo(() => Object.fromEntries(accounts.map((a) => [String(a.id), a])), [accounts]);
    const acctByCode = useMemo(() => Object.fromEntries(accounts.map((a) => [String(a.code), a])), [accounts]);

    const setLine = (i, key, val) => setData('lines', data.lines.map((l, idx) => (idx === i ? { ...l, [key]: val } : l)));
    const addLine = () => setData('lines', [...data.lines, emptyLine()]);
    const removeLine = (i) => setData('lines', data.lines.filter((_, idx) => idx !== i));

    // Rekomendasi akun (Tahap 2 automasi): cocokkan keterangan + memo dengan
    // aturan kata kunci, tampilkan kandidat akun COA yang bisa langsung dipakai.
    const suggestions = useMemo(() => {
        const text = [data.description, ...data.lines.map((l) => l.memo)].join(' ').toLowerCase();
        if (text.trim().length < 3) return [];
        const scored = [];
        recommendations.forEach((rule) => {
            const acc = acctByCode[rule.code];
            if (!acc) return;
            let score = 0;
            rule.keywords.forEach((kw) => { if (kw && text.includes(kw)) score += 1 + (kw.length >= 8 ? 1 : 0); });
            if (score > 0) scored.push({ acc, side: rule.side, score });
        });
        scored.sort((a, b) => b.score - a.score);
        // Buang duplikat akun, batasi 5 teratas.
        const seen = new Set();
        return scored.filter((s) => (seen.has(s.acc.id) ? false : seen.add(s.acc.id))).slice(0, 5);
    }, [data.description, data.lines, recommendations, acctByCode]);

    // Terapkan saran: isi baris kosong pertama (atau tambah baris) dengan akun.
    const applySuggestion = (acc, side) => {
        const idx = data.lines.findIndex((l) => !l.account_id);
        if (idx === -1) {
            setData('lines', [...data.lines, { ...emptyLine(), account_id: String(acc.id) }]);
        } else {
            setLine(idx, 'account_id', String(acc.id));
        }
    };

    const totalDebit = data.lines.reduce((s, l) => s + num(l.debit), 0);
    const totalCredit = data.lines.reduce((s, l) => s + num(l.credit), 0);
    const diff = Math.round((totalDebit - totalCredit) * 100) / 100;
    const hasValue = totalDebit > 0 || totalCredit > 0;
    const balanced = totalDebit > 0 && diff === 0;

    const insight = useMemo(() => {
        if (!hasValue || balanced) return null;
        const msgs = [];
        const abs = Math.abs(diff);
        if (diff > 0) msgs.push(`Total DEBET lebih besar ${fmt(abs)} dari kredit. Untuk seimbang, tambahkan KREDIT sebesar ${fmt(abs)} pada akun lawan (mis. Kas/Bank bila pembayaran, atau Utang bila berutang).`);
        else if (diff < 0) msgs.push(`Total KREDIT lebih besar ${fmt(abs)} dari debet. Untuk seimbang, tambahkan DEBET sebesar ${fmt(abs)} pada akun lawan (mis. Kas/Bank bila penerimaan, Piutang, atau akun Beban).`);
        data.lines.forEach((l, i) => {
            if (num(l.debit) > 0 && num(l.credit) > 0) msgs.push(`Baris ${i + 1}: satu akun terisi debet DAN kredit sekaligus — pisahkan ke dua baris.`);
        });
        return msgs;
    }, [data.lines, diff, hasValue, balanced]);

    const resetForm = () => { reset(); setEditingId(null); setShowForm(false); };
    const openNew = () => { reset(); setEditingId(null); setShowForm(true); };
    const openEdit = (e) => {
        setData({
            entry_date: e.entry_date, description: e.description,
            lines: e.lines.map((l) => ({ account_id: String(l.account_id), aux_code: l.aux_code || '', debit: l.debit || '', credit: l.credit || '', memo: l.memo || '' })),
            documents: [], action: 'save',
        });
        setEditingId(e.id);
        setShowForm(true);
    };

    const doSubmit = (action) => {
        const opts = { forceFormData: true, preserveScroll: true, onSuccess: () => resetForm() };
        if (editingId) {
            router.post(route('books.journal.update', editingId), { ...data, _method: 'put' }, opts);
        } else {
            transform((d) => ({ ...d, action }));
            post(route('books.journal.store'), opts);
        }
    };

    const submitEntry = (id) => router.post(route('books.journal.submit', id), {}, { preserveScroll: true });
    const approveEntry = (id) => router.post(route('books.journal.approve', id), {}, { preserveScroll: true });
    const rejectEntry = (id) => {
        const reason = window.prompt('Alasan penolakan:');
        if (reason) router.post(route('books.journal.reject', id), { reason }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Jurnal" />
            <AppLayout title="Jurnal Umum">
                <div className="flex flex-wrap items-center gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Tahun</label>
                        <input type="number" className="input w-28" defaultValue={year}
                            onBlur={(e) => router.get(route('books.journal.index'), { year: e.target.value })} />
                    </div>
                    {approve_level > 0 && (
                        <span className="badge badge-blue mt-5">Approver Level {approve_level}</span>
                    )}
                    <div className="flex-1" />
                    {can_create && (
                        <button onClick={() => (showForm ? resetForm() : openNew())} className="btn-primary">
                            {showForm ? '✕ Tutup Form' : '+ Jurnal Baru'}
                        </button>
                    )}
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                {can_create && showForm && (
                    <form onSubmit={(e) => { e.preventDefault(); doSubmit('save'); }} className="card mb-5 print:hidden">
                        {editingId && <p className="text-amber-300 text-sm mb-3">✎ Mengubah jurnal (draft/ditolak).</p>}
                        <div className="grid sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label className="label">Tanggal</label>
                                <input type="date" className="input" value={data.entry_date} onChange={(e) => setData('entry_date', e.target.value)} required />
                            </div>
                            <div>
                                <label className="label">Keterangan</label>
                                <input type="text" className="input" value={data.description} onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Contoh: Pembayaran ke vendor ..." required />
                            </div>
                        </div>

                        {suggestions.length > 0 && (
                            <div className="mb-4 rounded-lg border border-sky-500/30 bg-sky-500/10 p-3">
                                <p className="text-sky-300 text-xs font-semibold mb-2">💡 Saran akun (otomatis dari keterangan) — klik untuk memakai</p>
                                <div className="flex flex-wrap gap-2">
                                    {suggestions.map(({ acc, side }) => (
                                        <button key={acc.id} type="button" onClick={() => applySuggestion(acc, side)}
                                            className="inline-flex items-center gap-1.5 rounded-full bg-sky-600/20 hover:bg-sky-600/40 border border-sky-500/40 px-3 py-1 text-xs text-sky-100 transition-colors">
                                            <span className="font-mono">{acc.code}</span>
                                            <span>{acc.name}</span>
                                            <span className="text-sky-300/70">· {side === 'credit' ? 'Kredit' : 'Debet'}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="table-header">Akun</th>
                                        <th className="table-header">Kode Bantu</th>
                                        <th className="table-header text-right">Debet</th>
                                        <th className="table-header text-right">Kredit</th>
                                        <th className="table-header">Memo</th>
                                        <th className="table-header"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.lines.map((line, i) => {
                                        const acc = acctById[String(line.account_id)];
                                        return (
                                            <tr key={i} className="border-b border-slate-700/50">
                                                <td className="table-cell">
                                                    <select className="input min-w-[180px]" value={line.account_id} onChange={(e) => setLine(i, 'account_id', e.target.value)} required>
                                                        <option value="">— pilih akun —</option>
                                                        {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                                                    </select>
                                                    {acc?.normal_balance && <span className="text-[10px] text-slate-500">Normal: {acc.normal_balance === 'Kr' ? 'Kredit' : 'Debet'}</span>}
                                                </td>
                                                <td className="table-cell">
                                                    <input className="input w-28" list="aux-codes" value={line.aux_code} onChange={(e) => setLine(i, 'aux_code', e.target.value)} placeholder="mis. V-001" />
                                                </td>
                                                <td className="table-cell"><input type="number" step="0.01" min="0" className="input text-right w-32" value={line.debit} onChange={(e) => setLine(i, 'debit', e.target.value)} /></td>
                                                <td className="table-cell"><input type="number" step="0.01" min="0" className="input text-right w-32" value={line.credit} onChange={(e) => setLine(i, 'credit', e.target.value)} /></td>
                                                <td className="table-cell"><input type="text" className="input" value={line.memo} onChange={(e) => setLine(i, 'memo', e.target.value)} /></td>
                                                <td className="table-cell">{data.lines.length > 2 && <button type="button" onClick={() => removeLine(i)} className="text-red-400 hover:text-red-300 px-2">✕</button>}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t border-slate-600">
                                        <td colSpan={2} className="table-cell font-medium">Total</td>
                                        <td className="table-cell text-right font-bold">{fmt(totalDebit)}</td>
                                        <td className="table-cell text-right font-bold">{fmt(totalCredit)}</td>
                                        <td colSpan={2} className="table-cell">
                                            {balanced ? <span className="badge badge-green">✓ Seimbang</span> : <span className="badge badge-red">✗ Selisih {fmt(Math.abs(diff))}</span>}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                            <datalist id="aux-codes">
                                {aux_codes.map((c) => <option key={c.code} value={c.code}>{c.label}</option>)}
                            </datalist>
                        </div>

                        {insight && (
                            <div className="mt-4 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3">
                                <p className="text-amber-300 font-semibold text-sm mb-1">⚠️ Neraca belum seimbang — tidak dapat diajukan</p>
                                <ul className="list-disc ml-5 text-amber-100/90 text-sm space-y-1">
                                    {insight.map((m, i) => <li key={i}>{m}</li>)}
                                </ul>
                                <p className="text-amber-200/70 text-xs mt-2">Ingat posisi normal: Aset & Beban = Debet; Kewajiban, Modal & Pendapatan = Kredit.</p>
                            </div>
                        )}

                        <div className="mt-4">
                            <label className="label">Dokumen Pendukung (bisa lebih dari 1) — PDF/gambar/Office, maks 10MB</label>
                            <input type="file" multiple className="input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                onChange={(e) => setData('documents', Array.from(e.target.files))} />
                            {data.documents.length > 0 && <p className="text-slate-400 text-xs mt-1">{data.documents.length} berkas siap diunggah.</p>}
                        </div>

                        <div className="flex flex-wrap items-center gap-2 mt-4">
                            <button type="button" onClick={addLine} className="btn-secondary">+ Baris</button>
                            <span className="text-xs text-slate-500">Jurnal &gt; {fmt(threshold)} butuh approval Direktur (Level 2).</span>
                            <div className="flex-1" />
                            {editingId ? (
                                <button type="button" onClick={() => doSubmit('save')} className="btn-primary" disabled={processing || !balanced}>Simpan Perubahan</button>
                            ) : (
                                <>
                                    <button type="button" onClick={() => doSubmit('save')} className="btn-secondary" disabled={processing || !balanced}>Simpan Draft</button>
                                    <button type="button" onClick={() => doSubmit('submit')} className="btn-primary" disabled={processing || !balanced}>Simpan &amp; Ajukan Approval</button>
                                </>
                            )}
                        </div>
                        {!balanced && hasValue && <p className="text-right text-xs text-red-400 mt-1">Neraca harus seimbang.</p>}
                    </form>
                )}

                <div className="space-y-4">
                    {entries.length === 0 && (
                        <div className="card text-center text-slate-400">Belum ada jurnal untuk tahun {year}.</div>
                    )}
                    {entries.map((e) => {
                        const st = STATUS[e.status] || STATUS.draft;
                        return (
                            <div key={e.id} className="card">
                                <div className="flex flex-wrap items-center gap-2 mb-3 pb-3 border-b border-slate-700/70">
                                    <span className="font-mono text-xs text-slate-400">{e.entry_no}</span>
                                    <span className="text-slate-300">{tgl(e.entry_date)}</span>
                                    <span className="text-white font-medium">— {e.description}</span>
                                    <span className={`badge ${st.cls}`}>{st.label}{e.status === 'pending' ? ` L${e.current_level}` : ''}</span>
                                    {e.created_by && <span className="text-xs text-slate-500">oleh {e.created_by}</span>}
                                    <div className="flex-1" />
                                    <div className="flex flex-wrap gap-2 print:hidden">
                                        {e.can_approve && <button onClick={() => approveEntry(e.id)} className="text-emerald-400 hover:text-emerald-300 text-xs font-medium">✅ Setujui</button>}
                                        {e.can_approve && <button onClick={() => rejectEntry(e.id)} className="text-red-400 hover:text-red-300 text-xs font-medium">✕ Tolak</button>}
                                        {e.can_submit && <button onClick={() => submitEntry(e.id)} className="text-blue-400 hover:text-blue-300 text-xs">📤 Ajukan</button>}
                                        {e.can_edit && <button onClick={() => openEdit(e)} className="text-blue-400 hover:text-blue-300 text-xs">Edit</button>}
                                        {e.can_edit && <button onClick={() => router.delete(route('books.journal.destroy', e.id), { preserveScroll: true })} className="text-red-400 hover:text-red-300 text-xs">Hapus</button>}
                                    </div>
                                </div>

                                {e.status === 'rejected' && e.reject_reason && (
                                    <p className="text-red-300 text-xs mb-3">Ditolak: {e.reject_reason}</p>
                                )}

                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <tbody>
                                            {e.lines.map((l, i) => (
                                                <tr key={i} className="border-b border-slate-700/40">
                                                    <td className="table-cell font-mono text-xs w-24">{l.account_code}</td>
                                                    <td className="table-cell">{l.account_name}
                                                        {l.aux_code && <span className="badge badge-blue ml-2 text-[10px]">{l.aux_code}</span>}
                                                        {l.memo && <span className="text-slate-500 text-xs"> — {l.memo}</span>}
                                                    </td>
                                                    <td className="table-cell text-right w-32">{fmt(l.debit)}</td>
                                                    <td className="table-cell text-right w-32">{fmt(l.credit)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colSpan={2} className="table-cell text-right font-medium">Total</td>
                                                <td className="table-cell text-right font-bold">{fmt(e.total_debit)}</td>
                                                <td className="table-cell text-right font-bold">{fmt(e.total_credit)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                {e.attachments.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-2 items-center">
                                        <span className="text-slate-400 text-xs">Dokumen:</span>
                                        {e.attachments.map((a) => (
                                            <span key={a.id} className="inline-flex items-center gap-1 bg-slate-700/40 rounded px-2 py-1 text-xs">
                                                <a href={a.url} target="_blank" rel="noopener" className="text-blue-300 hover:text-blue-200">📎 {a.name}</a>
                                                {e.can_edit && <button onClick={() => router.delete(route('books.journal.attachment.destroy', a.id), { preserveScroll: true })} className="text-red-400 hover:text-red-300 print:hidden">✕</button>}
                                            </span>
                                        ))}
                                    </div>
                                )}

                                {e.approvals.length > 0 && (
                                    <div className="mt-3 text-xs text-slate-400 space-y-0.5 border-t border-slate-700/50 pt-2">
                                        {e.approvals.map((a, i) => (
                                            <p key={i}>
                                                {a.action === 'submitted' ? '📤 Diajukan' : a.action === 'approved' ? `✅ Disetujui L${a.level}` : `✕ Ditolak L${a.level}`}
                                                {' '}oleh {a.user || '—'}{a.notes ? ` — ${a.notes}` : ''} <span className="text-slate-600">{a.at}</span>
                                            </p>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </AppLayout>
        </>
    );
}
