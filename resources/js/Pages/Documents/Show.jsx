import { Head, Link, router } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import AppLayout from '@/Layouts/AppLayout';

// Jenis dokumen yang dahulu memuat kolom paraf "Mengetahui/Menyetujui Direksi".
// Kini pengesahan itu dipindah ke footer (di sebelah kode dokumen).
const APPROVAL_TYPES = ['kwitansi', 'voucher_jurnal', 'perjalanan_dinas', 'reimbursement', 'do'];

const num = (v) => parseFloat(v) || 0;
const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
const tgl = (s) => (s ? new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) : '—');
// Kolom default tabel isi Surat Resmi — dipakai sebagai fallback untuk dokumen lama yang belum menyimpan table_columns.
const DEFAULT_TABLE_COLUMNS = [
    { key: 'desc', label: 'Uraian', align: 'left' },
    { key: 'qty', label: 'Qty', align: 'right' },
    { key: 'unit', label: 'Satuan', align: 'left' },
    { key: 'keterangan', label: 'Keterangan', align: 'left' },
];

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

const TITLES = {
    invoice: 'INVOICE', faktur: 'FAKTUR PENJUALAN', kwitansi: 'KWITANSI',
    surat_jalan: 'SURAT JALAN', voucher_jurnal: 'VOUCHER JURNAL ENTRI',
    tanda_terima: 'TANDA TERIMA DOKUMEN/BARANG', perjalanan_dinas: 'SURAT PERJALANAN DINAS',
    reimbursement: 'FORM REIMBURSEMENT', po: 'PURCHASE ORDER', do: 'DELIVERY ORDER',
};

const STATUS_BADGE = {
    on_review: 'badge-amber', signed: 'badge-blue', released: 'badge-green', cancelled: 'badge-red',
};

// Kode unik dokumen (deterministik dari id).
const uniqueCode = (id) => 'GEP-' + String(id || '').replace(/-/g, '').slice(0, 10).toUpperCase();

/** Kop surat — hanya di header dokumen (poin 3.3). */
function Kop({ company }) {
    return (
        <div className="flex items-center gap-4 border-b-2 border-slate-800 pb-4 mb-6">
            <img src={company.logo} alt="Logo" className="w-16 h-16 object-contain" />
            <div className="flex-1">
                <h1 className="text-xl font-bold text-slate-900 leading-tight">{company.name}</h1>
                <p className="text-xs text-slate-600 mt-1">{company.address}</p>
                <p className="text-xs text-slate-500">Website: {company.website} &nbsp;|&nbsp; Email: {company.email}</p>
                <p className="text-xs text-slate-500">Telp. : {company.phone}</p>
            </div>
        </div>
    );
}

/** Tanda tangan Direksi (poin 3.4) — Hormat Kami / PT Geosys / (ruang materai) / Nama / Jabatan. */
function DirekturSign({ company, meta }) {
    return (
        <div className="flex justify-end mt-10 text-sm text-slate-700">
            <div className="w-64 text-center">
                <p>Hormat Kami,</p>
                <p className="font-semibold">{company.name}</p>
                {/* ruang setinggi materai + tanda tangan */}
                <div style={{ height: '90px' }} />
                <p className="font-semibold underline uppercase">{meta.extra?.signer_name || '(_____________________)'}</p>
                <p className="text-slate-600">{meta.extra?.signer_title || 'Direktur Utama'}</p>
            </div>
        </div>
    );
}

/** Tanda tangan personil ybs. + paraf Direksi (mengetahui/menyetujui).
 *  Bila dokumen dirilis oleh Direksi sendiri, Direksi menjadi penandatangan.
 *  Khusus Tanda Terima: sisi kiri menjadi "Diterima oleh" berkolom kosong
 *  (Instansi, tanda tangan, Nama, Jabatan) untuk diisi manual oleh penerima. */
function PersonnelSign({ company, meta, director, type }) {
    if (type === 'tanda_terima') {
        const BlankLine = ({ w = 'w-36' }) => (
            <span className={`inline-block border-b border-dotted border-slate-500 ${w} align-bottom`}>&nbsp;</span>
        );
        return (
            <div className="flex justify-between mt-10 text-sm text-slate-700">
                {/* Diterima oleh — seluruh kolom diisi manual oleh penerima */}
                <div className="w-64">
                    <p className="text-center mb-3">Diterima oleh,</p>
                    <div className="space-y-3">
                        <p>Instansi&nbsp;: <BlankLine w="w-36" /></p>
                        <div>
                            <p>Tanda tangan&nbsp;:</p>
                            <div style={{ height: '60px' }} />
                        </div>
                        <p>Nama&nbsp;: <BlankLine w="w-40" /></p>
                        <p>Jabatan&nbsp;: <BlankLine w="w-36" /></p>
                    </div>
                </div>
                {/* Hormat Kami — pihak yang menyerahkan (PT GEP) */}
                <div className="w-56 text-center">
                    <p>Hormat Kami,</p>
                    <p className="font-semibold">{company.name}</p>
                    <div style={{ height: '84px' }} />
                    <p className="font-semibold underline uppercase">{director || meta.extra?.signer_name || '(_____________________)'}</p>
                    <p className="text-slate-600">{director ? 'Direksi' : (meta.extra?.signer_title || 'Personil')}</p>
                </div>
            </div>
        );
    }
    if (director) {
        return (
            <div className="flex justify-end mt-10 text-sm text-slate-700">
                <div className="w-64 text-center">
                    <p>Hormat Kami,</p>
                    <p className="font-semibold">{company.name}</p>
                    <div style={{ height: '90px' }} />
                    <p className="font-semibold underline uppercase">{director}</p>
                    <p className="text-slate-600">Direksi</p>
                </div>
            </div>
        );
    }
    // Kolom paraf "Mengetahui/Menyetujui Direksi" dipindah ke footer;
    // di sini tersisa satu kolom tanda tangan personil ybs.
    return (
        <div className="flex justify-end mt-10 text-sm text-slate-700">
            <div className="w-64 text-center">
                <p>Hormat Kami,</p>
                <div style={{ height: '84px' }} />
                <p className="font-semibold underline uppercase">{meta.extra?.signer_name || '(_____________________)'}</p>
                <p className="text-slate-600">{meta.extra?.signer_title || 'Personil'}</p>
            </div>
        </div>
    );
}

/** Footer dokumen (poin 3.5) — website, halaman, kode unik.
 *  approval: nota "Mengetahui/Menyetujui Direksi" (dipindah dari area TTD). */
function Footer({ company, code, approval }) {
    return (
        <div className="doc-footer mt-10 pt-3 border-t border-slate-300 flex flex-wrap items-end justify-between gap-3 text-[10px] text-slate-500">
            <span>Website: {company.website}</span>
            <span className="doc-page">Halaman 1</span>
            <div className="text-right leading-relaxed">
                <div>Kode Dokumen: {code}</div>
                {approval && <div>{approval}</div>}
            </div>
        </div>
    );
}

/** QR verifikasi keaslian (dokumen terkunci) — dipasang di kanan atas. */
function VerifyQR({ url }) {
    return (
        <div className="flex flex-col items-end mt-2">
            <QRCodeSVG value={url} size={64} level="M" marginSize={0} bgColor="#ffffff" fgColor="#0f172a" />
            <span className="text-[9px] text-slate-500 mt-0.5">Pindai untuk verifikasi</span>
        </div>
    );
}

export default function DocumentsShow({ document, config, company, statuses, can_release, can_edit, is_locked }) {
    const m = document.meta || {};
    const title = TITLES[document.type] || (config.label || '').toUpperCase();
    const total = num(m.amounts?.total);
    const code = uniqueCode(document.id);
    const status = document.status || 'on_review';

    // Pengesahan Direksi dipindah ke footer (di sebelah kode dokumen).
    const approval = APPROVAL_TYPES.includes(document.type)
        ? 'Mengetahui/Menyetujui: Direksi PT Geosys Energi Prima'
        : null;

    // Dokumen yang sudah ditandatangani/dirilis dikunci & diberi QR verifikasi
    // yang menautkan ke halaman verifikasi publik (dapat dipindai siapa pun).
    const locked = ['signed', 'released'].includes(status);
    const verifyUrl = locked ? route('documents.verify', document.id) : null;

    const setStatus = (s) => router.patch(route('documents.status', document.id), { status: s }, { preserveScroll: true });

    return (
        <>
            <Head title={document.number} />
            <style>{`@media print {
                aside, header { display: none !important; }
                .app-viewport > .lg\\:hidden { display: none !important; }
                main { overflow: visible !important; height: auto !important; }
                .doc-toolbar { display: none !important; }
                .print-sheet { box-shadow: none !important; margin: 0 !important; max-width: 100% !important; }
                body { background: #fff !important; }
            }`}</style>
            <AppLayout title={config.label + ' — ' + document.number}>
                <div className="doc-toolbar flex flex-wrap items-center gap-2 mb-4">
                    <Link href={route('documents.index')} className="btn-secondary">← Kembali</Link>
                    <span className={`badge ${STATUS_BADGE[status] || 'badge-slate'}`}>{statuses?.[status] || status}</span>
                    {is_locked && <span className="badge badge-slate" title="Terkunci — hanya Super Admin yang dapat merevisi">🔒 Terkunci</span>}
                    {document.released_by && <span className="text-xs text-slate-400">Dirilis oleh {document.released_by}</span>}
                    <div className="flex-1" />
                    {can_edit && <Link href={route('documents.edit', document.id)} className="btn-secondary">✏️ Edit / Revisi</Link>}
                    {can_release && status !== 'signed' && <button onClick={() => setStatus('signed')} className="btn-secondary">✍️ Tandatangani</button>}
                    {can_release && status !== 'released' && <button onClick={() => setStatus('released')} className="btn-primary">✅ Rilis</button>}
                    {can_release && status !== 'cancelled' && <button onClick={() => setStatus('cancelled')} className="btn-danger">Batalkan</button>}
                    <button onClick={() => window.print()} className="btn-primary">🖨️ Cetak / PDF</button>
                    {can_release && <button onClick={() => router.delete(route('documents.destroy', document.id))} className="btn-danger">Hapus</button>}
                </div>

                <div className="print-sheet bg-white text-slate-800 rounded-lg shadow-card mx-auto p-8 sm:p-10 relative"
                    style={{ maxWidth: '800px' }}>
                    {status === 'cancelled' && (
                        <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <span className="text-red-500/20 font-black text-7xl rotate-[-25deg] tracking-widest">DIBATALKAN</span>
                        </div>
                    )}

                    {/* HEADER */}
                    <Kop company={company} />

                    {document.type === 'surat_resmi' ? (
                        <>
                            {/* BODY 1 — kepala surat resmi */}
                            <div className="flex justify-between items-start mb-4 text-sm">
                                <table className="text-slate-700">
                                    <tbody>
                                        <tr><td className="pr-3 text-slate-500 align-top">Tanggal</td><td className="align-top">: {tgl(document.doc_date)}</td></tr>
                                        <tr><td className="pr-3 text-slate-500 align-top">Nomor</td><td className="align-top font-mono">: {document.number}</td></tr>
                                        <tr><td className="pr-3 text-slate-500 align-top">Lampiran</td><td className="align-top">: {m.extra?.lampiran || '—'}</td></tr>
                                        <tr><td className="pr-3 text-slate-500 align-top">Sifat</td><td className="align-top">: {m.extra?.sifat || '—'}</td></tr>
                                        <tr><td className="pr-3 text-slate-500 align-top">Perihal</td><td className="align-top font-medium">: {m.extra?.perihal || '—'}</td></tr>
                                    </tbody>
                                </table>
                                <div className="flex flex-col items-end gap-2">
                                    <span className="border-2 border-slate-800 px-3 py-1 font-bold tracking-wider">
                                        {(m.extra?.scope || 'eksternal') === 'internal' ? 'INTERNAL' : 'EKSTERNAL'}
                                    </span>
                                    {verifyUrl && <VerifyQR url={verifyUrl} />}
                                </div>
                            </div>
                            {/* BODY 2 — kepada / dari */}
                            <div className="flex justify-between items-start mb-4 text-sm gap-6">
                                <div>
                                    <p>Yth,</p>
                                    <p className="font-semibold text-slate-900">{m.party?.name || '—'}</p>
                                    {m.party?.instansi && <p className="text-slate-800">{m.party.instansi}</p>}
                                    {m.party?.address && <p className="text-slate-600 whitespace-pre-line">{m.party.address}</p>}
                                </div>
                                {(m.extra?.scope || 'eksternal') === 'eksternal' && (m.party?.dari || m.party?.dari_alamat) && (
                                    <div className="text-right">
                                        <p className="text-slate-500">Dari:</p>
                                        <p className="font-semibold text-slate-900">{m.party?.dari || '—'}</p>
                                        {m.party?.dari_alamat && <p className="text-slate-600 whitespace-pre-line">{m.party.dari_alamat}</p>}
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        <div className="flex justify-between items-start mb-5">
                            <div>
                                <h2 className="text-2xl font-bold tracking-wide text-slate-900">{title}</h2>
                                <p className="text-sm text-slate-600">Nomor: <span className="font-mono font-semibold">{document.number}</span></p>
                            </div>
                            <div className="text-right text-sm text-slate-600 flex flex-col items-end">
                                <p>Tanggal: {tgl(document.doc_date)}</p>
                                {m.extra?.jatuh_tempo && <p>Jatuh Tempo: {tgl(m.extra.jatuh_tempo)}</p>}
                                {verifyUrl && <VerifyQR url={verifyUrl} />}
                            </div>
                        </div>
                    )}

                    {/* Pihak */}
                    {!['voucher_jurnal', 'perjalanan_dinas', 'surat_resmi'].includes(document.type) && (
                        <div className="mb-4 text-sm">
                            <p className="text-slate-500">{{
                                kwitansi: 'Telah terima dari', tanda_terima: 'Diterima dari',
                                reimbursement: 'Pemohon', po: 'Kepada (Vendor)',
                            }[document.type] || 'Kepada'}:</p>
                            <p className="font-semibold text-slate-900">{m.party?.name}</p>
                            {m.party?.address && <p className="text-slate-600">{m.party.address}</p>}
                        </div>
                    )}

                    {/* BODY 1 — Narasi pembuka */}
                    {m.extra?.narasi_pembuka && <p className="text-sm text-slate-700 mb-3">{m.extra.narasi_pembuka}</p>}

                    {/* Merujuk (khusus surat jalan) */}
                    {document.type === 'surat_jalan' && m.references && m.references.some((r) => (r.text || '').trim()) && (
                        <div className="mb-3 text-sm">
                            <p className="text-slate-700 font-medium">Merujuk:</p>
                            <ol className="list-decimal ml-6 space-y-1 text-slate-700">
                                {m.references.filter((r) => (r.text || '').trim()).map((r, i) => (
                                    <li key={i} className="whitespace-pre-line">{r.text}</li>
                                ))}
                            </ol>
                        </div>
                    )}

                    {/* BODY 2 — Dokumen referensi/underlying (opsional, bisa lebih dari satu) */}
                    {(() => {
                        const list = (m.extra?.ref_docs && m.extra.ref_docs.length)
                            ? m.extra.ref_docs.filter((r) => (r.no || '').trim() || (r.date || '').trim())
                            : ((m.extra?.ref_no || m.extra?.ref_date) ? [{ no: m.extra?.ref_no, date: m.extra?.ref_date }] : []);
                        if (!list.length) return null;
                        return (
                            <div className="text-sm text-slate-600 mb-4">
                                <span className="font-medium">Merujuk pada:</span>
                                <ol className="list-decimal ml-6 mt-1 space-y-0.5">
                                    {list.map((r, i) => (
                                        <li key={i}>{r.no || '—'}{r.date && <> &nbsp;tanggal {tgl(r.date)}</>}</li>
                                    ))}
                                </ol>
                            </div>
                        );
                    })()}

                    {/* Kwitansi */}
                    {document.type === 'kwitansi' && (
                        <div className="space-y-3 text-sm">
                            <div className="flex"><span className="w-40 text-slate-500">Uang sejumlah</span>
                                <span className="flex-1 font-medium italic bg-slate-50 rounded px-3 py-2">{terbilang(total)} rupiah</span></div>
                            <div className="flex"><span className="w-40 text-slate-500">Untuk pembayaran</span>
                                <span className="flex-1">{m.extra?.untuk_pembayaran}</span></div>
                            <div className="inline-block mt-2 bg-slate-900 text-white font-bold text-lg rounded px-4 py-2">Rp {fmt(total)}</div>
                        </div>
                    )}

                    {/* Invoice / Faktur */}
                    {['invoice', 'faktur', 'po'].includes(document.type) && (
                        <table className="w-full text-sm border border-slate-300">
                            <thead>
                                <tr className="bg-slate-100">
                                    <th className="border border-slate-300 px-3 py-2 text-left">Deskripsi</th>
                                    <th className="border border-slate-300 px-3 py-2 text-right">Qty</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left">Satuan</th>
                                    <th className="border border-slate-300 px-3 py-2 text-right">Harga</th>
                                    <th className="border border-slate-300 px-3 py-2 text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(m.items || []).map((it, i) => (
                                    <tr key={i}>
                                        <td className="border border-slate-300 px-3 py-2">{it.desc}</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right">{it.qty}</td>
                                        <td className="border border-slate-300 px-3 py-2">{it.unit}</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right">{fmt(it.price)}</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right">{fmt(num(it.qty) * num(it.price))}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr><td colSpan={4} className="border border-slate-300 px-3 py-2 text-right font-medium">Subtotal</td>
                                    <td className="border border-slate-300 px-3 py-2 text-right">{fmt(m.amounts?.subtotal)}</td></tr>
                                {num(m.amounts?.ppn) > 0 && (
                                    <tr><td colSpan={4} className="border border-slate-300 px-3 py-2 text-right">PPN 11%</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right">{fmt(m.amounts?.ppn)}</td></tr>
                                )}
                                <tr className="bg-slate-100 font-bold"><td colSpan={4} className="border border-slate-300 px-3 py-2 text-right">TOTAL</td>
                                    <td className="border border-slate-300 px-3 py-2 text-right">Rp {fmt(total)}</td></tr>
                            </tfoot>
                        </table>
                    )}
                    {['invoice', 'faktur', 'po'].includes(document.type) && (
                        <p className="text-sm italic text-slate-600 mt-2">Terbilang: {terbilang(total)} rupiah</p>
                    )}

                    {/* Surat Jalan */}
                    {document.type === 'surat_jalan' && (
                        <>
                            <div className="mb-4">
                                <p className="text-sm text-slate-500 mb-1">Kendaraan Pengangkut:</p>
                                <table className="w-full text-sm border border-slate-300">
                                    <thead>
                                        <tr className="bg-slate-100">
                                            <th className="border border-slate-300 px-3 py-2 text-left">Kendaraan</th>
                                            <th className="border border-slate-300 px-3 py-2 text-left">No. Polisi</th>
                                            <th className="border border-slate-300 px-3 py-2 text-left">Sopir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(m.vehicles && m.vehicles.length
                                            ? m.vehicles
                                            : (m.extra?.kendaraan ? [{ kendaraan: m.extra.kendaraan, no_polisi: m.extra.no_polisi, sopir: m.extra.sopir }] : [{}])
                                        ).map((veh, i) => (
                                            <tr key={i}>
                                                <td className="border border-slate-300 px-3 py-2">{veh.kendaraan || '—'}</td>
                                                <td className="border border-slate-300 px-3 py-2">{veh.no_polisi || '—'}</td>
                                                <td className="border border-slate-300 px-3 py-2">{veh.sopir || '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <table className="w-full text-sm border border-slate-300">
                                <thead>
                                    <tr className="bg-slate-100">
                                        <th className="border border-slate-300 px-3 py-2 text-left">Nama Barang</th>
                                        <th className="border border-slate-300 px-3 py-2 text-right">Qty</th>
                                        <th className="border border-slate-300 px-3 py-2 text-left">Satuan</th>
                                        <th className="border border-slate-300 px-3 py-2 text-left">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(m.items || []).map((it, i) => (
                                        <tr key={i}>
                                            <td className="border border-slate-300 px-3 py-2">{it.desc}</td>
                                            <td className="border border-slate-300 px-3 py-2 text-right">{it.qty}</td>
                                            <td className="border border-slate-300 px-3 py-2">{it.unit}</td>
                                            <td className="border border-slate-300 px-3 py-2">{it.keterangan}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </>
                    )}

                    {/* Voucher Jurnal Entri */}
                    {document.type === 'voucher_jurnal' && (
                        <table className="w-full text-sm border border-slate-300">
                            <thead>
                                <tr className="bg-slate-100">
                                    <th className="border border-slate-300 px-3 py-2 text-left">Akun</th>
                                    <th className="border border-slate-300 px-3 py-2 text-right">Debet</th>
                                    <th className="border border-slate-300 px-3 py-2 text-right">Kredit</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(m.lines || []).map((l, i) => (
                                    <tr key={i}>
                                        <td className="border border-slate-300 px-3 py-2">{l.account}</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right">{l.debit ? fmt(l.debit) : '—'}</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right">{l.credit ? fmt(l.credit) : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="bg-slate-100 font-bold">
                                    <td className="border border-slate-300 px-3 py-2 text-right">Total</td>
                                    <td className="border border-slate-300 px-3 py-2 text-right">{fmt((m.lines || []).reduce((s, l) => s + num(l.debit), 0))}</td>
                                    <td className="border border-slate-300 px-3 py-2 text-right">{fmt((m.lines || []).reduce((s, l) => s + num(l.credit), 0))}</td>
                                </tr>
                            </tfoot>
                        </table>
                    )}

                    {/* Tanda Terima / Delivery Order — daftar barang (qty) */}
                    {['tanda_terima', 'do'].includes(document.type) && (
                        <table className="w-full text-sm border border-slate-300">
                            <thead>
                                <tr className="bg-slate-100">
                                    <th className="border border-slate-300 px-3 py-2 text-left">{document.type === 'tanda_terima' ? 'Dokumen / Barang' : 'Nama Barang'}</th>
                                    <th className="border border-slate-300 px-3 py-2 text-right">Qty</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left">Satuan</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(m.items || []).map((it, i) => (
                                    <tr key={i}>
                                        <td className="border border-slate-300 px-3 py-2">{it.desc}</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right">{it.qty}</td>
                                        <td className="border border-slate-300 px-3 py-2">{it.unit}</td>
                                        <td className="border border-slate-300 px-3 py-2">{it.keterangan}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    {/* Perjalanan Dinas — detail perjalanan */}
                    {document.type === 'perjalanan_dinas' && (
                        <table className="w-full text-sm mb-4">
                            <tbody>
                                {[
                                    ['Nama Pegawai', m.party?.name],
                                    ['Jabatan', m.extra?.jabatan],
                                    ['Tujuan', m.extra?.tujuan],
                                    ['Keperluan', m.extra?.keperluan],
                                    ['Tanggal Berangkat', m.extra?.tgl_berangkat ? tgl(m.extra.tgl_berangkat) : '—'],
                                    ['Tanggal Kembali', m.extra?.tgl_kembali ? tgl(m.extra.tgl_kembali) : '—'],
                                    ['Transportasi', m.extra?.transport],
                                ].map(([k, v], i) => (
                                    <tr key={i} className="border-b border-slate-200">
                                        <td className="py-2 pr-4 text-slate-500 w-48 align-top">{k}</td>
                                        <td className="py-2 text-slate-800">{v || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    {/* Rincian Item Biaya (Perjalanan Dinas & Reimbursement) */}
                    {['perjalanan_dinas', 'reimbursement'].includes(document.type) && (
                        <>
                            <table className="w-full text-sm border border-slate-300">
                                <thead>
                                    <tr className="bg-slate-100">
                                        {document.type === 'reimbursement' && <th className="border border-slate-300 px-3 py-2 text-left">Tanggal</th>}
                                        <th className="border border-slate-300 px-3 py-2 text-left">Uraian / Transportasi</th>
                                        <th className="border border-slate-300 px-3 py-2 text-right">Nominal</th>
                                        <th className="border border-slate-300 px-3 py-2 text-right">Qty</th>
                                        <th className="border border-slate-300 px-3 py-2 text-right">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(m.cost_items || []).map((it, i) => (
                                        <tr key={i}>
                                            {document.type === 'reimbursement' && <td className="border border-slate-300 px-3 py-2">{it.tanggal ? tgl(it.tanggal) : '—'}</td>}
                                            <td className="border border-slate-300 px-3 py-2">{it.uraian}</td>
                                            <td className="border border-slate-300 px-3 py-2 text-right">{fmt(it.nominal)}</td>
                                            <td className="border border-slate-300 px-3 py-2 text-right">{it.qty}</td>
                                            <td className="border border-slate-300 px-3 py-2 text-right">{fmt(num(it.nominal) * num(it.qty))}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="bg-slate-100 font-bold">
                                        <td colSpan={document.type === 'reimbursement' ? 4 : 3} className="border border-slate-300 px-3 py-2 text-right">TOTAL</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right">Rp {fmt(total)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            <p className="text-sm italic text-slate-600 mt-2">Terbilang: {terbilang(total)} rupiah</p>
                        </>
                    )}

                    {/* Surat Resmi — BODY 4: isi + PIC */}
                    {document.type === 'surat_resmi' && (
                        <div className="text-sm text-slate-700">
                            {m.extra?.content_mode === 'table' ? (
                                <table className="w-full text-sm border border-slate-300 mb-3">
                                    <thead>
                                        <tr className="bg-slate-100">
                                            {(m.extra?.table_columns || DEFAULT_TABLE_COLUMNS).map((col) => (
                                                <th key={col.key} className={`border border-slate-300 px-3 py-2 ${col.align === 'right' ? 'text-right' : 'text-left'}`}>{col.label}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(m.items || []).map((it, i) => (
                                            <tr key={i}>
                                                {(m.extra?.table_columns || DEFAULT_TABLE_COLUMNS).map((col) => (
                                                    <td key={col.key} className={`border border-slate-300 px-3 py-2 ${col.align === 'right' ? 'text-right' : ''}`}>{it[col.key]}</td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                m.extra?.content_text && <p className="whitespace-pre-line mb-3">{m.extra.content_text}</p>
                            )}
                            <p>
                                Untuk konfirmasi atau pertanyaan lebih lanjut dapat menghubungi
                                {' '}<span className="font-medium">{m.extra?.pic_nama || 'PIC kami'}</span>
                                {m.extra?.pic_kontak ? ` (${m.extra.pic_kontak})` : ''} atau melalui email perusahaan {company.email}.
                            </p>
                        </div>
                    )}

                    {document.notes && <p className="text-sm text-slate-600 mt-4"><span className="font-medium">Catatan:</span> {document.notes}</p>}

                    {/* Narasi penutup */}
                    {m.extra?.narasi_penutup && <p className="text-sm text-slate-700 mt-4">{m.extra.narasi_penutup}</p>}

                    {/* TANDA TANGAN */}
                    {document.type === 'surat_jalan'
                        ? <div className="grid grid-cols-3 gap-4 mt-10 text-sm text-slate-700 text-center">
                            <div>
                                <p className="mb-1 font-medium">Perwakilan Driver</p>
                                <p className="text-xs text-slate-500 mb-14">&nbsp;</p>
                                <p className="border-t border-slate-400 pt-1">(____________________)</p>
                            </div>
                            <div>
                                <p className="mb-1 font-medium">Penerima</p>
                                <p className="text-xs text-slate-500 mb-14">
                                    {(() => {
                                        const nm = (m.party?.name || '').trim();
                                        if (!nm) return 'PLTU ……………';
                                        return /^pltu\b/i.test(nm) ? nm : 'PLTU ' + nm;
                                    })()}
                                </p>
                                <p className="border-t border-slate-400 pt-1">(____________________)</p>
                            </div>
                            <div>
                                <p className="mb-1 font-medium">PT Geosys Energi Prima</p>
                                <p className="text-xs text-slate-500 mb-14">K3</p>
                                <p className="border-t border-slate-400 pt-1">(____________________)</p>
                            </div>
                          </div>
                        : ['kwitansi', 'voucher_jurnal', 'tanda_terima', 'perjalanan_dinas', 'reimbursement', 'do'].includes(document.type)
                        ? <PersonnelSign company={company} meta={m} director={document.released_by} type={document.type} />
                        : <DirekturSign company={company} meta={m} />}

                    {/* Tembusan (khusus surat resmi) */}
                    {document.type === 'surat_resmi' && (
                        <div className="mt-8 text-sm text-slate-700">
                            <p className="font-medium">Tembusan:</p>
                            <ol className="list-decimal ml-6">
                                {[...(m.tembusan || []).filter((t) => (t.nama || '').trim()).map((t) => t.nama), 'Arsip'].map((t, i) => (
                                    <li key={i}>{t}</li>
                                ))}
                            </ol>
                        </div>
                    )}

                    {/* FOOTER */}
                    <Footer company={company} code={code} approval={approval} />
                </div>
            </AppLayout>
        </>
    );
}
