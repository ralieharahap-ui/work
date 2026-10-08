import { Head, useForm, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Fragment, useRef, useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
const tgl = (s) => s ? new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const size = (b) => !b ? '' : b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`;

const CATEGORY_BADGE = { bank: 'badge-amber', investor: 'badge-blue', investor_internal: 'badge-green' };
const PERIOD_LABEL   = { tahun: '% Per- Tahun', bulan: '% Per- Bulan' };
const PERIOD_SHORT   = { tahun: '%/th', bulan: '%/bln' };

const DEFAULT_CATEGORIES = { bank: 'Bank', investor: 'Investor Eksternal', investor_internal: 'Investor Internal (Pihak Berelasi)' };

export default function Creditors({ creditors, summary, can_manage, can_delete = false, can_upload, categories = DEFAULT_CATEGORIES, doc_types = [] }) {
    const [editing, setEditing] = useState(null); // null = tertutup, {} = baru, {..} = edit
    const [openDocs, setOpenDocs] = useState(null); // id kreditur yang panel dokumennya terbuka
    const contractInput = useRef(null);

    const blank = { code: '', name: '', category: 'bank', phone: '', address: '', account_no: '', interest_rate: '', interest_period: 'tahun', loan_ceiling: '', maturity_date: '', payable_balance: 0, is_active: true, contracts: [] };
    const { data, setData, post, transform, processing, reset, errors, progress } = useForm(blank);

    const openNew = () => { reset(); setData(blank); setEditing({}); };
    const openEdit = (c) => {
        setData({
            code: c.code, name: c.name, category: c.category, phone: c.phone || '', address: c.address || '',
            account_no: c.account_no || '', interest_rate: c.interest_rate || '', interest_period: c.interest_period || 'tahun',
            loan_ceiling: c.loan_ceiling || '', maturity_date: c.maturity_date || '', payable_balance: c.opening_balance || 0,
            is_active: c.is_active, contracts: [],
        });
        setEditing(c);
    };

    const submit = (e) => {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            forceFormData: data.contracts.length > 0,
            onSuccess: () => { reset(); setEditing(null); if (contractInput.current) contractInput.current.value = ''; },
        };
        if (editing && editing.id) {
            // POST + _method=PUT agar file kontrak ikut terkirim (multipart)
            transform((d) => ({ ...d, _method: 'put' }));
            post(route('books.creditors.update', editing.id), opts);
        } else {
            transform((d) => d);
            post(route('books.creditors.store'), opts);
        }
    };

    const contractErrors = Object.entries(errors).filter(([k]) => k.startsWith('contracts')).map(([, v]) => v);

    return (
        <>
            <Head title="Kreditur Pendanaan" />
            <AppLayout title="Kreditur Pendanaan (Investor & Bank)">
                <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <div className="card">
                        <p className="text-slate-400 text-xs">Total Kewajiban ke Bank</p>
                        <p className="text-amber-300 font-bold text-lg mt-1">{fmt(summary.total_bank)}</p>
                    </div>
                    <div className="card">
                        <p className="text-slate-400 text-xs">Total Kewajiban ke Investor Eksternal</p>
                        <p className="text-amber-300 font-bold text-lg mt-1">{fmt(summary.total_investor)}</p>
                    </div>
                    <div className="card">
                        <p className="text-slate-400 text-xs">Total Kewajiban ke Investor Internal (Pihak Berelasi)</p>
                        <p className="text-amber-300 font-bold text-lg mt-1">{fmt(summary.total_investor_internal)}</p>
                    </div>
                    <div className="card">
                        <p className="text-slate-400 text-xs">Jatuh Tempo &lt; 30 Hari</p>
                        <p className={`font-bold text-lg mt-1 ${summary.due_soon_count > 0 ? 'text-red-400' : 'text-slate-200'}`}>{summary.due_soon_count}</p>
                    </div>
                </div>

                <div className="flex items-center gap-3 mb-4">
                    <p className="text-slate-400 text-sm flex-1">Kode kreditur dipakai sebagai <b>kode bantu</b> pada baris jurnal kewajiban pendanaan.</p>
                    {can_manage && <button onClick={openNew} className="btn-primary">+ Kreditur</button>}
                </div>

                {editing && (
                    <form onSubmit={submit} className="card mb-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label className="label">Kode</label>
                            <input className="input" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="BANK-001" required />
                            {errors.code && <p className="text-red-400 text-xs mt-1">{errors.code}</p>}
                        </div>
                        <div>
                            <label className="label">Nama Kreditur</label>
                            <input className="input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        </div>
                        <div>
                            <label className="label">Kategori</label>
                            <select className="input" value={data.category} onChange={(e) => setData('category', e.target.value)}>
                                {Object.entries(categories).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                            {errors.category && <p className="text-red-400 text-xs mt-1">{errors.category}</p>}
                        </div>
                        <div>
                            <label className="label">No. Rekening</label>
                            <input className="input" value={data.account_no} onChange={(e) => setData('account_no', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Telepon</label>
                            <input className="input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Alamat</label>
                            <input className="input" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Bunga</label>
                            <div className="flex gap-2">
                                <input type="number" step="0.01" className="input flex-1 min-w-0" value={data.interest_rate} onChange={(e) => setData('interest_rate', e.target.value)} placeholder="0.00" />
                                <select className="input w-auto" value={data.interest_period} onChange={(e) => setData('interest_period', e.target.value)}>
                                    <option value="tahun">{PERIOD_LABEL.tahun}</option>
                                    <option value="bulan">{PERIOD_LABEL.bulan}</option>
                                </select>
                            </div>
                            {errors.interest_period && <p className="text-red-400 text-xs mt-1">{errors.interest_period}</p>}
                        </div>
                        <div>
                            <label className="label">Plafon Pinjaman (Rp)</label>
                            <input type="number" step="0.01" className="input" value={data.loan_ceiling} onChange={(e) => setData('loan_ceiling', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Jatuh Tempo</label>
                            <input type="date" className="input" value={data.maturity_date} onChange={(e) => setData('maturity_date', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Saldo Kewajiban Awal (Rp)</label>
                            <input type="number" step="0.01" className="input" value={data.payable_balance} onChange={(e) => setData('payable_balance', e.target.value)} />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="label">Dokumen Underlying (Kontrak/Perjanjian)</label>
                            <input ref={contractInput} type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                className="input file:mr-3 file:rounded file:border-0 file:bg-slate-600 file:px-3 file:py-1 file:text-slate-100"
                                onChange={(e) => setData('contracts', Array.from(e.target.files || []))} />
                            <p className="text-slate-500 text-xs mt-1">
                                Bisa pilih lebih dari satu file (PDF/gambar/Word, maks. 10 MB per file).
                                {editing.id && ' File baru akan ditambahkan ke kontrak yang sudah ada.'}
                            </p>
                            {data.contracts.length > 0 && <p className="text-slate-300 text-xs mt-1">{data.contracts.length} file dipilih: {data.contracts.map((f) => f.name).join(', ')}</p>}
                            {progress && <p className="text-blue-300 text-xs mt-1">Mengunggah… {progress.percentage}%</p>}
                            {contractErrors.map((m, i) => <p key={i} className="text-red-400 text-xs mt-1">{m}</p>)}
                        </div>
                        <label className="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Aktif
                        </label>
                        <div className="sm:col-span-2 lg:col-span-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setEditing(null)} className="btn-secondary">Batal</button>
                            <button type="submit" className="btn-primary" disabled={processing}>{editing.id ? 'Simpan Perubahan' : 'Simpan'}</button>
                        </div>
                    </form>
                )}

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Kode</th>
                                <th className="table-header">Nama</th>
                                <th className="table-header">Kategori</th>
                                <th className="table-header">Jatuh Tempo</th>
                                <th className="table-header">Bunga</th>
                                <th className="table-header text-right">Saldo Kewajiban</th>
                                <th className="table-header">Status</th>
                                <th className="table-header">Dokumen</th>
                                <th className="table-header"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {creditors.length === 0 && (
                                <tr><td colSpan={9} className="table-cell text-center text-slate-400">Belum ada kreditur pendanaan.</td></tr>
                            )}
                            {creditors.map((c) => (
                                <Fragment key={c.id}>
                                    <tr className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                        <td className="table-cell font-mono text-xs">{c.code}</td>
                                        <td className="table-cell">{c.name}</td>
                                        <td className="table-cell">
                                            <span className={`badge ${CATEGORY_BADGE[c.category] || 'badge-slate'}`}>{categories[c.category] || c.category}</span>
                                        </td>
                                        <td className={`table-cell text-xs ${c.due_soon ? 'text-red-400 font-medium' : 'text-slate-400'}`}>{tgl(c.maturity_date)}</td>
                                        <td className="table-cell text-slate-400 text-xs">{c.interest_rate ? `${c.interest_rate}${PERIOD_SHORT[c.interest_period] || PERIOD_SHORT.tahun}` : '—'}</td>
                                        <td className="table-cell text-right font-medium text-amber-300" title={`Awal ${fmt(c.opening_balance)} + mutasi ${fmt(c.movement)}`}>{fmt(c.current_balance)}</td>
                                        <td className="table-cell">
                                            <span className={`badge ${c.is_active ? 'badge-green' : 'badge-slate'}`}>{c.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                        </td>
                                        <td className="table-cell whitespace-nowrap">
                                            <button onClick={() => setOpenDocs(openDocs === c.id ? null : c.id)}
                                                className={`text-xs px-2 py-1 rounded border ${openDocs === c.id ? 'border-blue-400 text-blue-300 bg-blue-500/10' : 'border-slate-600 text-slate-300 hover:border-blue-400 hover:text-blue-300'}`}>
                                                📎 {can_upload ? 'Upload / ' : ''}Lihat / Unduh ({c.documents?.length || 0}) {openDocs === c.id ? '▴' : '▾'}
                                            </button>
                                        </td>
                                        <td className="table-cell whitespace-nowrap">
                                            <Link href={route('books.creditors.show', c.id)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Riwayat</Link>
                                            {can_manage && (
                                                <button onClick={() => openEdit(c)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Edit</button>
                                            )}
                                            {can_delete && (
                                                <button onClick={() => confirm(`Hapus kreditur ${c.name} beserta seluruh dokumennya?`) && router.delete(route('books.creditors.destroy', c.id), { preserveScroll: true })}
                                                    className="text-red-400 hover:text-red-300 text-xs">Hapus</button>
                                            )}
                                        </td>
                                    </tr>
                                    {openDocs === c.id && (
                                        <tr className="border-b border-slate-700/50 bg-slate-900/40">
                                            <td colSpan={9} className="p-3">
                                                <DocumentPanel creditor={c} docTypes={doc_types} canUpload={can_upload} />
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}

/** Panel dokumen per kreditur: upload, lihat (read) & unduh per jenis dokumen. */
function DocumentPanel({ creditor, docTypes, canUpload }) {
    return (
        <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
            {docTypes.map((t) => (
                <DocTypeBox key={t.key} type={t} creditor={creditor} canUpload={canUpload}
                    docs={(creditor.documents || []).filter((d) => d.doc_type === t.key)} />
            ))}
        </div>
    );
}

function DocTypeBox({ type, creditor, docs, canUpload }) {
    const input = useRef(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const upload = (fileList) => {
        const files = Array.from(fileList || []);
        if (!files.length) return;
        setError(null);
        setBusy(true);
        router.post(route('books.creditors.documents.upload', creditor.id), { doc_type: type.key, files }, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errs) => setError(Object.values(errs)[0] || 'Gagal mengunggah dokumen.'),
            onFinish: () => { setBusy(false); if (input.current) input.current.value = ''; },
        });
    };

    const remove = (d) => {
        if (!confirm(`Hapus dokumen "${d.original_name}"?`)) return;
        router.delete(route('books.creditors.documents.destroy', d.id), { preserveScroll: true });
    };

    return (
        <div className="rounded-lg border border-slate-700 bg-slate-800/60 p-3 flex flex-col">
            <div className="flex items-start justify-between gap-2 mb-2">
                <div>
                    <p className="text-slate-100 text-sm font-medium">{type.label}</p>
                    <p className="text-slate-500 text-[11px]">{type.multiple ? 'Bisa multi upload' : 'Satu file (upload baru menggantikan)'}</p>
                </div>
                {canUpload && (
                    <>
                        <input ref={input} type="file" className="hidden" multiple={type.multiple}
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                            onChange={(e) => upload(e.target.files)} />
                        <button type="button" disabled={busy} onClick={() => input.current?.click()}
                            className="text-xs px-2 py-1 rounded bg-blue-600 hover:bg-blue-500 text-white disabled:opacity-50 whitespace-nowrap">
                            {busy ? 'Mengunggah…' : '⬆ Upload'}
                        </button>
                    </>
                )}
            </div>
            {error && <p className="text-red-400 text-xs mb-2">{error}</p>}
            {docs.length === 0 ? (
                <p className="text-slate-500 text-xs italic">Belum ada dokumen.</p>
            ) : (
                <ul className="space-y-1.5">
                    {docs.map((d) => (
                        <li key={d.id} className="text-xs border-t border-slate-700/60 pt-1.5">
                            <p className="text-slate-200 break-all" title={d.original_name}>{d.original_name}</p>
                            <p className="text-slate-500 text-[11px]">{size(d.size)}{d.uploaded_at ? ` · ${tgl(d.uploaded_at)}` : ''}</p>
                            <div className="flex gap-3 mt-0.5">
                                <a href={route('books.creditors.documents.view', d.id)} target="_blank" rel="noopener noreferrer" className="text-blue-400 hover:text-blue-300">👁 Lihat</a>
                                <a href={route('books.creditors.documents.download', d.id)} className="text-emerald-400 hover:text-emerald-300">⬇ Unduh</a>
                                {canUpload && <button type="button" onClick={() => remove(d)} className="text-red-400 hover:text-red-300">Hapus</button>}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
