import { Head } from '@inertiajs/react';

const tgl = (s) => (s ? new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) : '—');
const waktu = (s) => (s ? new Date(s).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' WIB' : null);

export default function DocumentVerify({ document, trail, company }) {
    return (
        <>
            <Head title={'Verifikasi ' + document.number} />
            <div className="theme-native min-h-screen bg-slate-100 text-slate-800 px-4 py-8">
                <div className="max-w-md mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
                    <div className="bg-slate-900 text-white px-5 py-4">
                        <p className="text-xs uppercase tracking-widest text-slate-400">Verifikasi Dokumen</p>
                        <p className="font-semibold">{company.name}</p>
                    </div>

                    <div className="px-5 py-4 border-b border-slate-200 text-sm space-y-1">
                        <p><span className="text-slate-500">Jenis:</span> {document.type_label}</p>
                        <p><span className="text-slate-500">Nomor:</span> <span className="font-mono font-semibold">{document.number}</span></p>
                        <p><span className="text-slate-500">Tanggal:</span> {tgl(document.doc_date)}</p>
                        {document.perihal && <p><span className="text-slate-500">Perihal:</span> {document.perihal}</p>}
                    </div>

                    <div className="px-5 py-5">
                        <p className="text-xs uppercase tracking-wider text-slate-500 mb-3">Alur pembuatan dokumen</p>
                        <ol className="relative border-l-2 border-slate-200 ml-2 space-y-5">
                            {trail.map((s, i) => {
                                const done = !!s.name && !!s.at;
                                return (
                                    <li key={i} className="ml-5">
                                        <span className={`absolute -left-[9px] w-4 h-4 rounded-full border-2 ${done ? 'bg-green-600 border-green-600' : 'bg-white border-slate-300'}`} />
                                        <p className="text-xs text-slate-500">{i + 1}. {s.label}</p>
                                        <p className={`font-medium ${done ? 'text-slate-900' : 'text-slate-400'}`}>{s.name || 'Belum dilakukan'}</p>
                                        {s.at && <p className="text-xs text-slate-500">{waktu(s.at)}</p>}
                                    </li>
                                );
                            })}
                        </ol>
                    </div>

                    <div className="px-5 pb-6">
                        {document.is_valid ? (
                            <div className="rounded-lg bg-green-50 border border-green-300 px-4 py-3 text-center">
                                <p className="text-green-700 font-bold text-lg">✔ Dokumen Sah</p>
                                <p className="text-green-700 text-sm">Dokumen telah ditandatangani dan dirilis.</p>
                            </div>
                        ) : document.status === 'cancelled' ? (
                            <div className="rounded-lg bg-red-50 border border-red-300 px-4 py-3 text-center">
                                <p className="text-red-700 font-bold">✖ Dokumen Dibatalkan</p>
                                <p className="text-red-700 text-sm">Dokumen ini tidak berlaku.</p>
                            </div>
                        ) : (
                            <div className="rounded-lg bg-amber-50 border border-amber-300 px-4 py-3 text-center">
                                <p className="text-amber-700 font-bold">Belum Dirilis</p>
                                <p className="text-amber-700 text-sm">
                                    {document.is_signed ? 'Dokumen telah ditandatangani, menunggu rilis.' : 'Dokumen masih dalam proses.'}
                                </p>
                            </div>
                        )}
                    </div>
                </div>
                <p className="text-center text-xs text-slate-400 mt-4">{company.website}</p>
            </div>
        </>
    );
}
