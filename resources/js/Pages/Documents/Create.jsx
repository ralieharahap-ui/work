import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useMemo } from 'react';

const num = (v) => parseFloat(v) || 0;
const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

/** Terbilang (angka -> kata bahasa Indonesia). */
function terbilang(n) {
    n = Math.floor(Math.abs(Number(n) || 0));
    const satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    const f = (x) => {
        if (x < 12) return satuan[x];
        if (x < 20) return f(x - 10) + ' belas';
        if (x < 100) return f(Math.floor(x / 10)) + ' puluh' + (x % 10 ? ' ' + f(x % 10) : '');
        if (x < 200) return 'seratus' + (x - 100 ? ' ' + f(x - 100) : '');
        if (x < 1000) return f(Math.floor(x / 100)) + ' ratus' + (x % 100 ? ' ' + f(x % 100) : '');
        if (x < 2000) return 'seribu' + (x - 1000 ? ' ' + f(x - 1000) : '');
        if (x < 1e6) return f(Math.floor(x / 1000)) + ' ribu' + (x % 1000 ? ' ' + f(x % 1000) : '');
        if (x < 1e9) return f(Math.floor(x / 1e6)) + ' juta' + (x % 1e6 ? ' ' + f(x % 1e6) : '');
        if (x < 1e12) return f(Math.floor(x / 1e9)) + ' miliar' + (x % 1e9 ? ' ' + f(x % 1e9) : '');
        return f(Math.floor(x / 1e12)) + ' triliun' + (x % 1e12 ? ' ' + f(x % 1e12) : '');
    };
    if (n === 0) return 'nol';
    const w = f(n).replace(/\s+/g, ' ').trim();
    return w.charAt(0).toUpperCase() + w.slice(1);
}

const emptyItem = () => ({ desc: '', qty: '', unit: 'Ton', price: '', keterangan: '' });
// Kolom default tabel isi Surat Resmi — dapat disesuaikan pengguna (tambah/kurang kolom, ubah nama header).
const DEFAULT_TABLE_COLUMNS = [
    { key: 'desc', label: 'Uraian', align: 'left' },
    { key: 'qty', label: 'Qty', align: 'right' },
    { key: 'unit', label: 'Satuan', align: 'left' },
    { key: 'keterangan', label: 'Keterangan', align: 'left' },
];
const emptyTableRow = (cols) => Object.fromEntries(cols.map((c) => [c.key, '']));
const emptyLine = () => ({ account: '', debit: '', credit: '' });
const emptyCostItem = () => ({ tanggal: '', uraian: '', nominal: '', qty: 1 });
const emptyTembusan = () => ({ nama: '' });
const emptyVehicle = () => ({ kendaraan: '', no_polisi: '', sopir: '' });
const emptyRef = () => ({ text: '' });

// Narasi pembuka & penutup default per jenis (tone formil & profesional; dapat diedit).
const NARASI = {
    invoice: {
        pembuka: 'Dengan hormat, bersama ini kami sampaikan tagihan (invoice) atas transaksi dengan rincian sebagai berikut:',
        penutup: 'Demikian invoice ini kami sampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.',
    },
    faktur: {
        pembuka: 'Dengan hormat, berikut kami sampaikan faktur penjualan atas barang/jasa dengan rincian sebagai berikut:',
        penutup: 'Demikian faktur ini dibuat untuk dapat dipergunakan sebagaimana mestinya. Atas kerja samanya kami ucapkan terima kasih.',
    },
    kwitansi: {
        pembuka: 'Telah diterima pembayaran dengan rincian sebagai berikut:',
        penutup: 'Demikian kwitansi ini dibuat sebagai bukti pembayaran yang sah.',
    },
    surat_jalan: {
        pembuka: 'Bersama ini telah kami sampaikan Detail Pengiriman barang sebagai berikut:',
        penutup: 'Demikian surat jalan ini dibuat untuk dapat dipergunakan sebagaimana mestinya. Atas kerja samanya kami ucapkan terima kasih.',
    },
    voucher_jurnal: {
        pembuka: 'Dengan ini dicatat transaksi jurnal dengan rincian sebagai berikut:',
        penutup: 'Demikian voucher jurnal entri ini dibuat sebagai bukti pencatatan yang sah.',
    },
    tanda_terima: {
        pembuka: 'Dengan hormat, telah kami terima dokumen/barang dengan rincian sebagai berikut:',
        penutup: 'Demikian tanda terima ini dibuat sebagai bukti serah terima yang sah.',
    },
    perjalanan_dinas: {
        pembuka: 'Dengan hormat, bersama ini kami sampaikan surat perjalanan dinas dengan rincian sebagai berikut:',
        penutup: 'Demikian surat perjalanan dinas ini dibuat untuk dapat dipergunakan sebagaimana mestinya.',
    },
    reimbursement: {
        pembuka: 'Dengan hormat, bersama ini kami ajukan penggantian biaya (reimbursement) dengan rincian sebagai berikut:',
        penutup: 'Demikian pengajuan reimbursement ini kami sampaikan. Atas persetujuannya kami ucapkan terima kasih.',
    },
    po: {
        pembuka: 'Dengan hormat, bersama ini kami sampaikan pesanan pembelian (Purchase Order) dengan rincian sebagai berikut:',
        penutup: 'Demikian Purchase Order ini kami sampaikan. Mohon konfirmasi ketersediaan dan pengiriman sesuai ketentuan.',
    },
    do: {
        pembuka: 'Dengan hormat, bersama ini kami sampaikan perintah pengiriman (Delivery Order) dengan rincian sebagai berikut:',
        penutup: 'Demikian Delivery Order ini dibuat untuk dapat dipergunakan sebagaimana mestinya.',
    },
    surat_resmi: {
        pembuka: 'Sehubungan dengan hal tersebut di atas, dengan ini PT Geosys Energi Prima menyampaikan hal-hal sebagai berikut:',
        penutup: 'Demikian surat ini kami sampaikan. Atas perhatian dan kerja samanya kami ucapkan terima kasih.',
    },
};

const emptyRefDoc = () => ({ no: '', date: '' });

const partyLabel = (type) => ({
    kwitansi: 'Telah terima dari',
    surat_jalan: 'Kepada (Penerima)',
    do: 'Kepada (Penerima)',
    tanda_terima: 'Diterima dari',
    po: 'Kepada (Vendor)',
    perjalanan_dinas: 'Nama Pegawai',
    reimbursement: 'Nama Pemohon',
}[type] || 'Kepada (Nama)');

const commonExtra = (type) => ({
    perihal: '',
    ref_docs: [emptyRefDoc()], // dokumen referensi/underlying — bisa lebih dari satu
    narasi_pembuka: NARASI[type]?.pembuka || '',
    narasi_penutup: NARASI[type]?.penutup || '',
    signer_name: '', signer_title: 'Direktur Utama',
});

function initMeta(type) {
    const ex = commonExtra(type);
    const base = { party: { name: '', address: '' }, amounts: {}, extra: ex };
    if (type === 'invoice' || type === 'faktur') {
        return { ...base, items: [emptyItem()], ppn_enabled: type === 'faktur', extra: { ...ex, jatuh_tempo: '' } };
    }
    if (type === 'kwitansi') {
        return { party: { name: '' }, amounts: { total: '' }, extra: { ...ex, untuk_pembayaran: '', tempat: 'Jakarta' } };
    }
    if (type === 'surat_jalan') {
        return { ...base, references: [emptyRef(), emptyRef()], items: [emptyItem()], vehicles: [emptyVehicle()], extra: ex };
    }
    if (type === 'voucher_jurnal') {
        return { party: { name: '' }, lines: [emptyLine(), emptyLine()], amounts: {}, extra: { ...ex, keterangan: '' } };
    }
    if (type === 'tanda_terima' || type === 'do') {
        return { ...base, items: [emptyItem()], extra: ex };
    }
    if (type === 'po') {
        return { ...base, items: [emptyItem()], ppn_enabled: false, extra: ex };
    }
    if (type === 'reimbursement') {
        return { party: { name: '' }, cost_items: [emptyCostItem()], amounts: {}, extra: { ...ex, debit_account_id: '', credit_account_id: '' } };
    }
    if (type === 'perjalanan_dinas') {
        return { party: { name: '' }, cost_items: [emptyCostItem()], amounts: {}, extra: { ...ex, jabatan: '', tujuan: '', keperluan: '', tgl_berangkat: '', tgl_kembali: '', transport: '', debit_account_id: '', credit_account_id: '' } };
    }
    if (type === 'surat_resmi') {
        return {
            party: { name: '', instansi: '', address: '', dari: '', dari_alamat: '' },
            items: [emptyItem()],
            tembusan: [emptyTembusan()],
            amounts: {},
            extra: { ...ex, signer_title: 'Manajer', scope: 'eksternal', sifat: 'Informasi', lampiran: '', content_mode: 'text', content_text: '', pic_nama: '', pic_kontak: '', table_columns: DEFAULT_TABLE_COLUMNS.map((c) => ({ ...c })) },
        };
    }
    return base;
}

export default function DocumentsCreate({ type, config, company, prefill, next_number, document = null }) {
    const isEdit = !!document;
    const { data, setData, post, put, processing, transform } = useForm({
        type,
        doc_date: document?.doc_date ?? new Date().toISOString().slice(0, 10),
        meta: document?.meta ?? initMeta(type),
        ref_type: document?.ref_type ?? null,
        ref_id: document?.ref_id ?? null,
        notes: document?.notes ?? '',
    });

    const meta = data.meta;
    const setMeta = (patch) => setData('meta', { ...meta, ...patch });
    const setParty = (k, v) => setMeta({ party: { ...meta.party, [k]: v } });
    const setExtra = (k, v) => setMeta({ extra: { ...meta.extra, [k]: v } });

    // ── Dokumen referensi/underlying (bisa lebih dari satu) ──
    const refDocs = meta.extra.ref_docs || [emptyRefDoc()];
    const setRefDoc = (i, k, v) => setExtra('ref_docs', refDocs.map((r, idx) => idx === i ? { ...r, [k]: v } : r));
    const addRefDoc = () => setExtra('ref_docs', [...refDocs, emptyRefDoc()]);
    const removeRefDoc = (i) => setExtra('ref_docs', refDocs.filter((_, idx) => idx !== i));

    // ── Items (invoice/faktur/surat_jalan) ──
    const setItem = (i, k, v) => setMeta({ items: meta.items.map((it, idx) => idx === i ? { ...it, [k]: v } : it) });
    const addItem = () => setMeta({ items: [...meta.items, emptyItem()] });
    const removeItem = (i) => setMeta({ items: meta.items.filter((_, idx) => idx !== i) });

    // ── Tabel isi Surat Resmi — kolom & baris custom ──
    const tableColumns = meta.extra?.table_columns || DEFAULT_TABLE_COLUMNS;
    const setTableColumnLabel = (key, label) => setExtra('table_columns', tableColumns.map((c) => c.key === key ? { ...c, label } : c));
    const addTableColumn = () => {
        const key = `col_${Date.now()}`;
        setMeta({
            extra: { ...meta.extra, table_columns: [...tableColumns, { key, label: 'Kolom Baru', align: 'left' }] },
            items: meta.items.map((it) => ({ ...it, [key]: '' })),
        });
    };
    const removeTableColumn = (key) => {
        if (tableColumns.length <= 1) return;
        setMeta({
            extra: { ...meta.extra, table_columns: tableColumns.filter((c) => c.key !== key) },
            items: meta.items.map((it) => { const { [key]: _drop, ...rest } = it; return rest; }),
        });
    };
    const addTableRow = () => setMeta({ items: [...meta.items, emptyTableRow(tableColumns)] });
    const removeTableRow = (i) => setMeta({ items: meta.items.filter((_, idx) => idx !== i) });

    // ── Referensi "Merujuk" (surat jalan) ──
    const setRef = (i, v) => setMeta({ references: meta.references.map((r, idx) => idx === i ? { text: v } : r) });
    const addRef = () => setMeta({ references: [...meta.references, emptyRef()] });
    const removeRef = (i) => setMeta({ references: meta.references.filter((_, idx) => idx !== i) });

    // ── Kendaraan (surat jalan, bisa lebih dari satu) ──
    const setVehicle = (i, k, v) => setMeta({ vehicles: meta.vehicles.map((veh, idx) => idx === i ? { ...veh, [k]: v } : veh) });
    const addVehicle = () => setMeta({ vehicles: [...meta.vehicles, emptyVehicle()] });
    const removeVehicle = (i) => setMeta({ vehicles: meta.vehicles.filter((_, idx) => idx !== i) });

    // ── Lines (voucher jurnal) ──
    const setLine = (i, k, v) => setMeta({ lines: meta.lines.map((l, idx) => idx === i ? { ...l, [k]: v } : l) });
    const addLine = () => setMeta({ lines: [...meta.lines, emptyLine()] });
    const removeLine = (i) => setMeta({ lines: meta.lines.filter((_, idx) => idx !== i) });

    // ── Rincian biaya (perjalanan dinas & reimbursement) ──
    const setCostItem = (i, k, v) => setMeta({ cost_items: meta.cost_items.map((it, idx) => idx === i ? { ...it, [k]: v } : it) });
    const addCostItem = () => setMeta({ cost_items: [...meta.cost_items, emptyCostItem()] });
    const removeCostItem = (i) => setMeta({ cost_items: meta.cost_items.filter((_, idx) => idx !== i) });

    // ── Tembusan (surat resmi) ──
    const setTembusan = (i, v) => setMeta({ tembusan: meta.tembusan.map((t, idx) => idx === i ? { nama: v } : t) });
    const addTembusan = () => setMeta({ tembusan: [...meta.tembusan, emptyTembusan()] });
    const removeTembusan = (i) => setMeta({ tembusan: meta.tembusan.filter((_, idx) => idx !== i) });

    const isItemDoc = ['invoice', 'faktur', 'surat_jalan', 'tanda_terima', 'do', 'po'].includes(type);
    const isValueDoc = ['invoice', 'faktur', 'po'].includes(type);
    const personnelSign = ['kwitansi', 'voucher_jurnal', 'tanda_terima', 'perjalanan_dinas', 'reimbursement', 'do'].includes(type);

    // ── Hitung total ──
    const { subtotal, ppn, total } = useMemo(() => {
        if (isValueDoc) {
            const sub = (meta.items || []).reduce((s, it) => s + num(it.qty) * num(it.price), 0);
            const p = meta.ppn_enabled ? sub * 0.11 : 0;
            return { subtotal: sub, ppn: p, total: sub + p };
        }
        if (type === 'kwitansi') return { subtotal: 0, ppn: 0, total: num(meta.amounts?.total) };
        if (type === 'reimbursement' || type === 'perjalanan_dinas') {
            const t = (meta.cost_items || []).reduce((s, it) => s + num(it.nominal) * num(it.qty), 0);
            return { subtotal: t, ppn: 0, total: t };
        }
        if (type === 'voucher_jurnal') {
            const d = (meta.lines || []).reduce((s, l) => s + num(l.debit), 0);
            return { subtotal: d, ppn: 0, total: d };
        }
        return { subtotal: 0, ppn: 0, total: 0 };
    }, [meta, type]);

    // ── Prefill dari sumber terkait ──
    const applyScenario = (id) => {
        const s = prefill.scenarios?.find((x) => x.id === id);
        if (!s) { setData((d) => ({ ...d, ref_type: null, ref_id: null })); return; }
        setData((d) => ({
            ...d, ref_type: 'scenario', ref_id: s.id,
            meta: {
                ...d.meta,
                items: [{ desc: 'Cangkang Sawit — ' + s.name, qty: s.volume, unit: 'Ton', price: s.price_customer, keterangan: '' }],
            },
        }));
    };
    const applyJournal = (id) => {
        const j = prefill.journals?.find((x) => x.id === id);
        if (!j) { setData((d) => ({ ...d, ref_type: null, ref_id: null })); return; }
        setData((d) => ({
            ...d, ref_type: 'journal_entry', ref_id: j.id, doc_date: j.entry_date,
            meta: {
                ...d.meta,
                lines: j.lines.map((l) => ({ account: l.account, debit: l.debit || '', credit: l.credit || '' })),
                extra: { ...d.meta.extra, keterangan: j.description },
            },
        }));
    };
    const applyCustomer = (id) => {
        const c = prefill.customers?.find((x) => x.id === id);
        if (!c) return;
        setData((d) => ({
            ...d, ref_type: 'customer', ref_id: c.id,
            meta: { ...d.meta, party: { name: c.customer_name || c.name, address: [c.city, c.province].filter(Boolean).join(', ') } },
        }));
    };
    const applyVendor = (id) => {
        const v = prefill.vendors?.find((x) => x.id === id);
        if (!v) return;
        setData((d) => ({
            ...d, ref_type: 'vendor', ref_id: v.id,
            meta: { ...d.meta, party: { name: v.name, address: v.address || '' } },
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        // Sertakan ringkasan nilai (subtotal/ppn/total) ke meta tepat sebelum kirim.
        transform((d) => ({
            ...d,
            meta: { ...d.meta, amounts: { ...d.meta.amounts, subtotal, ppn, total } },
        }));
        if (isEdit) put(route('documents.update', document.id));
        else post(route('documents.store'));
    };

    return (
        <>
            <Head title={(isEdit ? 'Revisi ' : 'Buat ') + config.label} />
            <AppLayout title={(isEdit ? 'Revisi ' : 'Buat ') + config.label}>
                <div className="mb-4 print:hidden">
                    <Link href={route('documents.index')} className="text-slate-400 hover:text-white text-sm">← Kembali ke Daftar Dokumen</Link>
                </div>

                <form onSubmit={submit} className="space-y-5 max-w-4xl">
                    <div className="card grid sm:grid-cols-3 gap-3">
                        <div>
                            <label className="label">Nomor Dokumen</label>
                            <input className="input font-mono" value={next_number} readOnly />
                        </div>
                        <div>
                            <label className="label">Tanggal</label>
                            <input type="date" className="input" value={data.doc_date}
                                onChange={(e) => setData('doc_date', e.target.value)} required />
                        </div>
                        <div className="flex items-end">
                            <span className="badge badge-blue">{config.icon} {config.label}</span>
                        </div>
                    </div>

                    {/* Perihal & dokumen referensi/underlying (opsional) */}
                    <div className="card grid sm:grid-cols-3 gap-3">
                        <div className="sm:col-span-3">
                            <label className="label">Perihal (untuk Dokumentasi)</label>
                            <input className="input" value={meta.extra.perihal || ''} onChange={(e) => setExtra('perihal', e.target.value)}
                                placeholder="mis. Penjualan cangkang sawit ke PLTU ..." />
                        </div>
                        <div className="sm:col-span-3">
                            <label className="label">Dokumen Referensi / Underlying (opsional — bisa lebih dari satu)</label>
                            <div className="space-y-2">
                                {refDocs.map((r, i) => (
                                    <div key={i} className="flex gap-2">
                                        <span className="text-slate-500 pt-2 w-5 text-right shrink-0">{i + 1}.</span>
                                        <input className="input flex-1" value={r.no} onChange={(e) => setRefDoc(i, 'no', e.target.value)} placeholder="mis. DO No. 1252.DO/DAN.01.01/..." />
                                        <input type="date" className="input w-40" value={r.date} onChange={(e) => setRefDoc(i, 'date', e.target.value)} />
                                        {refDocs.length > 1 && <button type="button" onClick={() => removeRefDoc(i)} className="text-red-400 hover:text-red-300 px-1 shrink-0">✕</button>}
                                    </div>
                                ))}
                            </div>
                            <button type="button" onClick={addRefDoc} className="btn-secondary mt-2">+ Referensi</button>
                        </div>
                    </div>

                    {/* Narasi pembuka & penutup (tone formil & profesional) */}
                    <div className="card grid gap-3">
                        <div>
                            <label className="label">Narasi Pembuka</label>
                            <textarea className="input" rows={2} value={meta.extra.narasi_pembuka || ''} onChange={(e) => setExtra('narasi_pembuka', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Narasi Penutup</label>
                            <textarea className="input" rows={2} value={meta.extra.narasi_penutup || ''} onChange={(e) => setExtra('narasi_penutup', e.target.value)} />
                        </div>
                        {type !== 'surat_jalan' && (
                            <div className="grid sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="label">Nama Penandatangan</label>
                                    <input className="input" value={meta.extra.signer_name || ''} onChange={(e) => setExtra('signer_name', e.target.value)}
                                        placeholder={personnelSign ? 'Nama personil/user ybs.' : 'mis. Ralie Akbar Harahap'} />
                                </div>
                                <div>
                                    <label className="label">Jabatan</label>
                                    {type === 'surat_resmi' ? (
                                        <select className="input" value={meta.extra.signer_title || 'Manajer'} onChange={(e) => setExtra('signer_title', e.target.value)}>
                                            <option>Manajer</option>
                                            <option>Direktur Utama</option>
                                            <option>Direktur Operasional</option>
                                        </select>
                                    ) : personnelSign ? (
                                        <input className="input" value={meta.extra.signer_title || ''} onChange={(e) => setExtra('signer_title', e.target.value)} placeholder="mis. Staf Operasional / Manajer Keuangan" />
                                    ) : (
                                        <select className="input" value={meta.extra.signer_title || 'Direktur Utama'} onChange={(e) => setExtra('signer_title', e.target.value)}>
                                            <option>Direktur Utama</option>
                                            <option>Direktur Operasional</option>
                                        </select>
                                    )}
                                </div>
                                {personnelSign && <p className="sm:col-span-2 text-slate-500 text-xs">Penandatangan utama = personil ybi. Direksi hanya membubuhkan paraf (mengetahui/menyetujui), kecuali Direksi sendiri yang merilis.</p>}
                            </div>
                        )}
                    </div>

                    {/* Prefill dari sumber terkait */}
                    {config.source === 'scenario' && prefill.scenarios?.length > 0 && (
                        <div className="card print:hidden">
                            <label className="label">Ambil dari Kalkulasi Proyek (opsional)</label>
                            <select className="input" onChange={(e) => applyScenario(e.target.value)}>
                                <option value="">— isi manual —</option>
                                {prefill.scenarios.map((s) => (
                                    <option key={s.id} value={s.id}>{s.name} — {fmt(s.total_revenue)}</option>
                                ))}
                            </select>
                        </div>
                    )}
                    {config.source === 'journal' && prefill.journals?.length > 0 && (
                        <div className="card print:hidden">
                            <label className="label">Ambil dari Jurnal yang Dirilis (opsional)</label>
                            <select className="input" onChange={(e) => applyJournal(e.target.value)}>
                                <option value="">— isi manual —</option>
                                {prefill.journals.map((j) => (
                                    <option key={j.id} value={j.id}>{j.entry_no} — {j.description}</option>
                                ))}
                            </select>
                        </div>
                    )}
                    {config.source === 'shipment' && prefill.customers?.length > 0 && (
                        <div className="card print:hidden">
                            <label className="label">Pilih Tujuan / Customer (opsional)</label>
                            <select className="input" onChange={(e) => applyCustomer(e.target.value)}>
                                <option value="">— isi manual —</option>
                                {prefill.customers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.customer_name || c.name}</option>
                                ))}
                            </select>
                        </div>
                    )}
                    {config.source === 'vendor' && prefill.vendors?.length > 0 && (
                        <div className="card print:hidden">
                            <label className="label">Pilih Vendor (opsional)</label>
                            <select className="input" onChange={(e) => applyVendor(e.target.value)}>
                                <option value="">— isi manual —</option>
                                {prefill.vendors.map((v) => (
                                    <option key={v.id} value={v.id}>{v.code} — {v.name}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Pihak / tujuan */}
                    {!['voucher_jurnal', 'surat_resmi'].includes(type) && (
                        <div className="card grid sm:grid-cols-2 gap-3">
                            <div>
                                <label className="label">{partyLabel(type)}</label>
                                <input className="input" value={meta.party?.name || ''}
                                    onChange={(e) => setParty('name', e.target.value)} required />
                            </div>
                            {!['kwitansi', 'perjalanan_dinas', 'reimbursement'].includes(type) && (
                                <div>
                                    <label className="label">Alamat</label>
                                    <input className="input" value={meta.party?.address || ''}
                                        onChange={(e) => setParty('address', e.target.value)} />
                                </div>
                            )}
                        </div>
                    )}

                    {/* Field spesifik: Surat Jalan — referensi & pengantar */}
                    {type === 'surat_jalan' && (
                        <div className="card">
                            <h3 className="text-white font-medium mb-1">Merujuk (Kontrak/SPK & DO dari customer)</h3>
                            <p className="text-slate-400 text-xs mb-3">Cantumkan nomor & tanggal Kontrak/SPK dan Delivery Order (DO) bulan berjalan yang diterbitkan customer.</p>
                            <div className="space-y-2">
                                {meta.references.map((r, i) => (
                                    <div key={i} className="flex gap-2">
                                        <span className="text-slate-500 pt-2 w-5 text-right shrink-0">{i + 1}.</span>
                                        <textarea className="input flex-1" rows={2} value={r.text}
                                            onChange={(e) => setRef(i, e.target.value)}
                                            placeholder={i === 0
                                                ? 'Perjanjian/SPK No. .... tanggal .... antara .... tentang ....'
                                                : 'Delivery Order (DO) No. .... tanggal .... ....'} />
                                        {meta.references.length > 1 && (
                                            <button type="button" onClick={() => removeRef(i)} className="text-red-400 hover:text-red-300 px-1 shrink-0">✕</button>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <button type="button" onClick={addRef} className="btn-secondary mt-3">+ Referensi</button>
                        </div>
                    )}

                    {/* Field spesifik: Surat Jalan — kendaraan bisa lebih dari satu */}
                    {type === 'surat_jalan' && (
                        <div className="card overflow-x-auto">
                            <h3 className="text-white font-medium mb-3">Kendaraan Pengangkut</h3>
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="table-header">Kendaraan</th>
                                        <th className="table-header">No. Polisi</th>
                                        <th className="table-header">Sopir</th>
                                        <th className="table-header"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {meta.vehicles.map((veh, i) => (
                                        <tr key={i} className="border-b border-slate-700/50">
                                            <td className="table-cell"><input className="input min-w-[150px]" value={veh.kendaraan} onChange={(e) => setVehicle(i, 'kendaraan', e.target.value)} placeholder="mis. Dump Truck" /></td>
                                            <td className="table-cell"><input className="input w-32" value={veh.no_polisi} onChange={(e) => setVehicle(i, 'no_polisi', e.target.value)} placeholder="BM 1234 XX" /></td>
                                            <td className="table-cell"><input className="input min-w-[140px]" value={veh.sopir} onChange={(e) => setVehicle(i, 'sopir', e.target.value)} /></td>
                                            <td className="table-cell">{meta.vehicles.length > 1 && <button type="button" onClick={() => removeVehicle(i)} className="text-red-400 hover:text-red-300 px-2">✕</button>}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <button type="button" onClick={addVehicle} className="btn-secondary mt-3">+ Kendaraan</button>
                        </div>
                    )}

                    {/* Field spesifik: Kwitansi */}
                    {type === 'kwitansi' && (
                        <div className="card grid sm:grid-cols-2 gap-3">
                            <div className="sm:col-span-2"><label className="label">Untuk Pembayaran</label>
                                <input className="input" value={meta.extra.untuk_pembayaran} onChange={(e) => setExtra('untuk_pembayaran', e.target.value)} required /></div>
                            <div><label className="label">Jumlah (Rp)</label>
                                <input type="number" min="0" step="0.01" className="input" value={meta.amounts.total}
                                    onChange={(e) => setMeta({ amounts: { ...meta.amounts, total: e.target.value } })} required /></div>
                            <div><label className="label">Tempat</label>
                                <input className="input" value={meta.extra.tempat} onChange={(e) => setExtra('tempat', e.target.value)} /></div>
                            <div className="sm:col-span-2 text-slate-400 text-sm italic">Terbilang: {terbilang(total)} rupiah</div>
                        </div>
                    )}

                    {/* Field spesifik: Invoice (jatuh tempo) */}
                    {type === 'invoice' && (
                        <div className="card">
                            <label className="label">Jatuh Tempo (opsional)</label>
                            <input type="date" className="input w-auto" value={meta.extra.jatuh_tempo}
                                onChange={(e) => setExtra('jatuh_tempo', e.target.value)} />
                        </div>
                    )}

                    {/* Field spesifik: Form Perjalanan Dinas */}
                    {type === 'perjalanan_dinas' && (
                        <div className="card grid sm:grid-cols-2 gap-3">
                            <div><label className="label">Jabatan</label>
                                <input className="input" value={meta.extra.jabatan} onChange={(e) => setExtra('jabatan', e.target.value)} /></div>
                            <div><label className="label">Tujuan</label>
                                <input className="input" value={meta.extra.tujuan} onChange={(e) => setExtra('tujuan', e.target.value)} /></div>
                            <div className="sm:col-span-2"><label className="label">Keperluan</label>
                                <textarea className="input" rows={2} value={meta.extra.keperluan} onChange={(e) => setExtra('keperluan', e.target.value)} /></div>
                            <div><label className="label">Tanggal Berangkat</label>
                                <input type="date" className="input" value={meta.extra.tgl_berangkat} onChange={(e) => setExtra('tgl_berangkat', e.target.value)} /></div>
                            <div><label className="label">Tanggal Kembali</label>
                                <input type="date" className="input" value={meta.extra.tgl_kembali} onChange={(e) => setExtra('tgl_kembali', e.target.value)} /></div>
                            <div><label className="label">Transportasi (umum)</label>
                                <input className="input" value={meta.extra.transport} onChange={(e) => setExtra('transport', e.target.value)} placeholder="mis. Pesawat / Mobil dinas" /></div>
                        </div>
                    )}

                    {/* Rincian Biaya (Perjalanan Dinas & Reimbursement) — bisa >1 item */}
                    {['perjalanan_dinas', 'reimbursement'].includes(type) && (
                        <div className="card overflow-x-auto">
                            <h3 className="text-white font-medium mb-3">Rincian Item Biaya</h3>
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        {type === 'reimbursement' && <th className="table-header">Tanggal</th>}
                                        <th className="table-header">Uraian / Transportasi</th>
                                        <th className="table-header text-right">Nominal</th>
                                        <th className="table-header text-right">Qty</th>
                                        <th className="table-header text-right">Jumlah</th>
                                        <th className="table-header"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {meta.cost_items.map((it, i) => (
                                        <tr key={i} className="border-b border-slate-700/50">
                                            {type === 'reimbursement' && <td className="table-cell"><input type="date" className="input w-40" value={it.tanggal} onChange={(e) => setCostItem(i, 'tanggal', e.target.value)} /></td>}
                                            <td className="table-cell"><input className="input min-w-[180px]" value={it.uraian} onChange={(e) => setCostItem(i, 'uraian', e.target.value)} placeholder="mis. Tiket pesawat / Hotel / Taksi" required /></td>
                                            <td className="table-cell"><input type="number" step="0.01" min="0" className="input w-32 text-right" value={it.nominal} onChange={(e) => setCostItem(i, 'nominal', e.target.value)} /></td>
                                            <td className="table-cell"><input type="number" step="1" min="1" className="input w-20 text-right" value={it.qty} onChange={(e) => setCostItem(i, 'qty', e.target.value)} /></td>
                                            <td className="table-cell text-right whitespace-nowrap">{fmt(num(it.nominal) * num(it.qty))}</td>
                                            <td className="table-cell">{meta.cost_items.length > 1 && <button type="button" onClick={() => removeCostItem(i)} className="text-red-400 hover:text-red-300 px-2">✕</button>}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t border-slate-600">
                                        <td colSpan={type === 'reimbursement' ? 4 : 3} className="table-cell text-right font-medium">Total</td>
                                        <td className="table-cell text-right font-bold text-blue-300">{fmt(total)}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <button type="button" onClick={addCostItem} className="btn-secondary mt-3">+ Item Biaya</button>
                            <p className="text-slate-400 text-sm italic mt-2">Terbilang: {terbilang(total)} rupiah</p>

                            <div className="grid sm:grid-cols-2 gap-3 mt-4 pt-4 border-t border-slate-700/70">
                                <p className="sm:col-span-2 text-slate-300 text-sm font-medium">Jurnal otomatis saat dokumen dirilis:</p>
                                <div>
                                    <label className="label">Akun Debit</label>
                                    <select className="input" value={meta.extra.debit_account_id || ''} onChange={(e) => setExtra('debit_account_id', e.target.value)}>
                                        <option value="">— pilih akun beban —</option>
                                        {(prefill.accounts || []).map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="label">Akun Kredit (lawan)</label>
                                    <select className="input" value={meta.extra.credit_account_id || ''} onChange={(e) => setExtra('credit_account_id', e.target.value)}>
                                        <option value="">— pilih akun lawan —</option>
                                        {(prefill.accounts || []).map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                                    </select>
                                </div>
                                <p className="sm:col-span-2 text-slate-500 text-xs">Jurnal: Debit akun terpilih & Kredit akun lawan sebesar {fmt(total)}. Default lazim: Debit Beban, Kredit 2-2400 Utang Pihak Berelasi.</p>
                            </div>
                        </div>
                    )}

                    {/* Field spesifik: Surat Resmi */}
                    {type === 'surat_resmi' && (
                        <>
                            <div className="card grid sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="label">Lingkup Surat</label>
                                    <select className="input" value={meta.extra.scope} onChange={(e) => setExtra('scope', e.target.value)}>
                                        <option value="eksternal">EKSTERNAL</option>
                                        <option value="internal">INTERNAL</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="label">Sifat</label>
                                    <select className="input" value={meta.extra.sifat} onChange={(e) => setExtra('sifat', e.target.value)}>
                                        <option>Rahasia</option><option>Urgent</option><option>Reminder</option><option>Informasi</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="label">Lampiran</label>
                                    <input className="input" value={meta.extra.lampiran} onChange={(e) => setExtra('lampiran', e.target.value)} placeholder="mis. 1 (satu) Berkas" />
                                </div>
                            </div>

                            <div className="card grid sm:grid-cols-2 gap-4">
                                <div className="space-y-3">
                                    <p className="text-slate-300 text-sm font-medium">Kepada (Tujuan)</p>
                                    <div><label className="label">Nama Lengkap yang Dituju</label>
                                        <input className="input" value={meta.party?.name || ''} onChange={(e) => setParty('name', e.target.value)} placeholder="mis. Bapak Ir. Budiman" required /></div>
                                    <div><label className="label">Nama Instansi</label>
                                        <input className="input" value={meta.party?.instansi || ''} onChange={(e) => setParty('instansi', e.target.value)} placeholder="mis. PT PLN Nusantara Power" /></div>
                                    <div><label className="label">Alamat Tujuan</label>
                                        <input className="input" value={meta.party?.address || ''} onChange={(e) => setParty('address', e.target.value)} /></div>
                                </div>
                                {meta.extra.scope === 'eksternal' && (
                                    <div className="space-y-3">
                                        <p className="text-slate-300 text-sm font-medium">Dari (Pengirim)</p>
                                        <div><label className="label">Nama Instansi</label>
                                            <input className="input" value={meta.party?.dari || ''} onChange={(e) => setParty('dari', e.target.value)} placeholder="mis. PT Geosys Energi Prima" /></div>
                                        <div><label className="label">Alamat</label>
                                            <input className="input" value={meta.party?.dari_alamat || ''} onChange={(e) => setParty('dari_alamat', e.target.value)} /></div>
                                    </div>
                                )}
                            </div>

                            <div className="card">
                                <div className="flex items-center gap-3 mb-3">
                                    <h3 className="text-white font-medium flex-1">Isi Surat (Body)</h3>
                                    <label className="flex items-center gap-1 text-xs text-slate-300"><input type="radio" name="cmode" checked={meta.extra.content_mode !== 'table'} onChange={() => setExtra('content_mode', 'text')} /> Narasi/Listing</label>
                                    <label className="flex items-center gap-1 text-xs text-slate-300"><input type="radio" name="cmode" checked={meta.extra.content_mode === 'table'} onChange={() => setExtra('content_mode', 'table')} /> Tabel</label>
                                </div>
                                {meta.extra.content_mode === 'table' ? (
                                    <div className="overflow-x-auto">
                                        <div className="flex items-center justify-between mb-2">
                                            <p className="text-xs text-slate-500">Kolom dapat disesuaikan: ubah nama header, tambah, atau kurangi kolom sesuai kebutuhan.</p>
                                            <button type="button" onClick={addTableColumn} className="btn-secondary text-xs shrink-0">+ Kolom</button>
                                        </div>
                                        <table className="w-full">
                                            <thead><tr className="border-b border-slate-700">
                                                {tableColumns.map((col) => (
                                                    <th key={col.key} className="table-header">
                                                        <div className="flex items-center gap-1">
                                                            <input className="input flex-1 min-w-[100px]" value={col.label} onChange={(e) => setTableColumnLabel(col.key, e.target.value)} placeholder="Nama kolom" />
                                                            {tableColumns.length > 1 && (
                                                                <button type="button" onClick={() => removeTableColumn(col.key)} className="text-red-400 hover:text-red-300 px-1 shrink-0" title="Hapus kolom">✕</button>
                                                            )}
                                                        </div>
                                                    </th>
                                                ))}
                                                <th className="table-header"></th>
                                            </tr></thead>
                                            <tbody>
                                                {meta.items.map((it, i) => (
                                                    <tr key={i} className="border-b border-slate-700/50">
                                                        {tableColumns.map((col) => (
                                                            <td key={col.key} className="table-cell">
                                                                <input className={`input min-w-[120px] ${col.align === 'right' ? 'text-right' : ''}`} value={it[col.key] || ''} onChange={(e) => setItem(i, col.key, e.target.value)} />
                                                            </td>
                                                        ))}
                                                        <td className="table-cell">{meta.items.length > 1 && <button type="button" onClick={() => removeTableRow(i)} className="text-red-400 hover:text-red-300 px-2">✕</button>}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                        <button type="button" onClick={addTableRow} className="btn-secondary mt-3">+ Baris</button>
                                    </div>
                                ) : (
                                    <textarea className="input" rows={5} value={meta.extra.content_text} onChange={(e) => setExtra('content_text', e.target.value)} placeholder="Tuliskan poin/isi surat. Gunakan baris baru untuk listing." />
                                )}
                                <div className="grid sm:grid-cols-2 gap-3 mt-4 pt-3 border-t border-slate-700/70">
                                    <div><label className="label">PIC (Nama)</label><input className="input" value={meta.extra.pic_nama} onChange={(e) => setExtra('pic_nama', e.target.value)} /></div>
                                    <div><label className="label">PIC (Kontak)</label><input className="input" value={meta.extra.pic_kontak} onChange={(e) => setExtra('pic_kontak', e.target.value)} placeholder="mis. 0812xxxx / email" /></div>
                                </div>
                            </div>

                            <div className="card">
                                <label className="label">Tembusan (akan otomatis ditambah "Arsip" di akhir)</label>
                                <div className="space-y-2">
                                    {meta.tembusan.map((t, i) => (
                                        <div key={i} className="flex gap-2">
                                            <span className="text-slate-500 pt-2 w-5 text-right shrink-0">{i + 1}.</span>
                                            <input className="input flex-1" value={t.nama} onChange={(e) => setTembusan(i, e.target.value)} placeholder="mis. Direktur Operasional" />
                                            {meta.tembusan.length > 1 && <button type="button" onClick={() => removeTembusan(i)} className="text-red-400 hover:text-red-300 px-1 shrink-0">✕</button>}
                                        </div>
                                    ))}
                                </div>
                                <button type="button" onClick={addTembusan} className="btn-secondary mt-2">+ Tembusan</button>
                            </div>
                        </>
                    )}

                    {/* Item baris (invoice/faktur/surat_jalan) */}
                    {isItemDoc && (
                        <div className="card overflow-x-auto">
                            <div className="flex items-center justify-between mb-3">
                                <h3 className="text-white font-medium">Rincian Barang</h3>
                                {isValueDoc && (
                                    <label className="flex items-center gap-2 text-sm text-slate-300">
                                        <input type="checkbox" checked={!!meta.ppn_enabled}
                                            onChange={(e) => setMeta({ ppn_enabled: e.target.checked })} /> PPN 11%
                                    </label>
                                )}
                            </div>
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="table-header">Deskripsi</th>
                                        <th className="table-header text-right">Qty</th>
                                        <th className="table-header">Satuan</th>
                                        {isValueDoc && <th className="table-header text-right">Harga</th>}
                                        {isValueDoc && <th className="table-header text-right">Jumlah</th>}
                                        {['surat_jalan', 'tanda_terima', 'do'].includes(type) && <th className="table-header">Keterangan</th>}
                                        <th className="table-header"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {meta.items.map((it, i) => (
                                        <tr key={i} className="border-b border-slate-700/50">
                                            <td className="table-cell"><input className="input min-w-[180px]" value={it.desc} onChange={(e) => setItem(i, 'desc', e.target.value)} required /></td>
                                            <td className="table-cell"><input type="number" step="0.01" className="input w-24 text-right" value={it.qty} onChange={(e) => setItem(i, 'qty', e.target.value)} /></td>
                                            <td className="table-cell"><input className="input w-20" value={it.unit} onChange={(e) => setItem(i, 'unit', e.target.value)} /></td>
                                            {isValueDoc && <td className="table-cell"><input type="number" step="0.01" className="input w-32 text-right" value={it.price} onChange={(e) => setItem(i, 'price', e.target.value)} /></td>}
                                            {isValueDoc && <td className="table-cell text-right whitespace-nowrap">{fmt(num(it.qty) * num(it.price))}</td>}
                                            {['surat_jalan', 'tanda_terima', 'do'].includes(type) && <td className="table-cell"><input className="input" value={it.keterangan} onChange={(e) => setItem(i, 'keterangan', e.target.value)} /></td>}
                                            <td className="table-cell">{meta.items.length > 1 && <button type="button" onClick={() => removeItem(i)} className="text-red-400 hover:text-red-300 px-2">✕</button>}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                {isValueDoc && (
                                    <tfoot>
                                        <tr><td colSpan={4} className="table-cell text-right font-medium">Subtotal</td><td className="table-cell text-right font-medium">{fmt(subtotal)}</td><td></td></tr>
                                        {meta.ppn_enabled && <tr><td colSpan={4} className="table-cell text-right">PPN 11%</td><td className="table-cell text-right">{fmt(ppn)}</td><td></td></tr>}
                                        <tr className="border-t border-slate-600"><td colSpan={4} className="table-cell text-right font-bold">Total</td><td className="table-cell text-right font-bold text-blue-300">{fmt(total)}</td><td></td></tr>
                                    </tfoot>
                                )}
                            </table>
                            <button type="button" onClick={addItem} className="btn-secondary mt-3">+ Baris</button>
                        </div>
                    )}

                    {/* Voucher Jurnal Entri */}
                    {type === 'voucher_jurnal' && (
                        <div className="card overflow-x-auto">
                            <div className="mb-3">
                                <label className="label">Keterangan</label>
                                <input className="input" value={meta.extra.keterangan} onChange={(e) => setExtra('keterangan', e.target.value)} required />
                            </div>
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="table-header">Akun</th>
                                        <th className="table-header text-right">Debet</th>
                                        <th className="table-header text-right">Kredit</th>
                                        <th className="table-header"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {meta.lines.map((l, i) => (
                                        <tr key={i} className="border-b border-slate-700/50">
                                            <td className="table-cell"><input className="input min-w-[200px]" value={l.account} onChange={(e) => setLine(i, 'account', e.target.value)} required /></td>
                                            <td className="table-cell"><input type="number" step="0.01" className="input w-32 text-right" value={l.debit} onChange={(e) => setLine(i, 'debit', e.target.value)} /></td>
                                            <td className="table-cell"><input type="number" step="0.01" className="input w-32 text-right" value={l.credit} onChange={(e) => setLine(i, 'credit', e.target.value)} /></td>
                                            <td className="table-cell">{meta.lines.length > 2 && <button type="button" onClick={() => removeLine(i)} className="text-red-400 hover:text-red-300 px-2">✕</button>}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t border-slate-600">
                                        <td className="table-cell text-right font-medium">Total</td>
                                        <td className="table-cell text-right font-bold">{fmt(meta.lines.reduce((s, l) => s + num(l.debit), 0))}</td>
                                        <td className="table-cell text-right font-bold">{fmt(meta.lines.reduce((s, l) => s + num(l.credit), 0))}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <button type="button" onClick={addLine} className="btn-secondary mt-3">+ Baris</button>
                        </div>
                    )}

                    <div className="card">
                        <label className="label">Catatan (opsional)</label>
                        <textarea className="input" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>

                    <div className="flex justify-end gap-2 print:hidden">
                        <Link href={route('documents.index')} className="btn-secondary">Batal</Link>
                        <button type="submit" className="btn-primary" disabled={processing}>{isEdit ? 'Simpan Revisi' : 'Simpan & Buat Dokumen'}</button>
                    </div>
                </form>
            </AppLayout>
        </>
    );
}
