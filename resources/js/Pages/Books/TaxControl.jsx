import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import { InformationCircleIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.abs(Number(n) || 0));
const signed = (n) => `${Number(n) < 0 ? '(' : ''}${fmt(n)}${Number(n) < 0 ? ')' : ''}`;

function Breakdown({ title, data }) {
    return (
        <div className="card">
            <p className="text-slate-300 text-sm font-medium mb-3">{title}</p>
            {data.account ? (
                <>
                    <p className="text-slate-500 text-xs mb-2">{data.account.code} — {data.account.name}</p>
                    <p className="text-2xl font-bold text-amber-300 mb-1">{signed(data.balance)}</p>
                    <p className="text-slate-500 text-xs mb-3">Mutasi tahun berjalan: {signed(data.movement_year)}</p>
                    {data.breakdown.length > 0 && (
                        <div className="border-t border-slate-700 pt-2 mt-2 space-y-1 max-h-40 overflow-y-auto">
                            {data.breakdown.map((b, i) => (
                                <div key={i} className="flex justify-between text-xs">
                                    <span className="text-slate-400 font-mono">{b.aux_code}</span>
                                    <span className={b.movement < 0 ? 'text-red-400' : 'text-slate-300'}>{signed(b.movement)}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <p className="text-slate-500 text-xs">Akun belum tersedia di COA — jalankan seeder Chart of Accounts.</p>
            )}
        </div>
    );
}

export default function TaxControl({ year, ppn_keluaran, ppn_masukan, ppn_wapu, selisih, wapu_outstanding, missing_accounts }) {
    const [yr, setYr] = useState(year);
    const reload = () => router.get(route('books.tax-control'), { year: yr }, { preserveState: true });

    return (
        <>
            <Head title="Kontrol PPN" />
            <AppLayout title="Kontrol Saldo PPN Masukan / Keluaran">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Periode (Tahun)</label>
                        <input type="number" className="input w-28" value={yr}
                            onChange={(e) => setYr(e.target.value)} onBlur={reload} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                {missing_accounts.length > 0 && (
                    <div className="card mb-4 border border-amber-500/30 flex items-start gap-2">
                        <ExclamationTriangleIcon className="w-5 h-5 text-amber-400 shrink-0 mt-0.5" />
                        <p className="text-amber-300 text-sm">
                            Akun berikut belum ada di Chart of Accounts: <b>{missing_accounts.join(', ')}</b>. Jalankan seeder ChartOfAccountsSeeder agar kontrol PPN akurat.
                        </p>
                    </div>
                )}

                <div className="card mb-4 flex items-start gap-2 bg-blue-950/30 border border-blue-500/20">
                    <InformationCircleIcon className="w-5 h-5 text-blue-400 shrink-0 mt-0.5" />
                    <p className="text-slate-300 text-sm leading-relaxed">
                        <b>Mekanisme WAPU:</b> untuk customer berstatus WAPU (Wajib Pungut), PPN atas penjualan <i>tidak</i> masuk ke kas perusahaan —
                        kas yang diterima = total tagihan − PPN yang dipungut sendiri oleh WAPU. PPN tersebut dicatat sementara di akun clearing
                        <b> {ppn_wapu.account?.code ?? '11507'} PPN Dipungut Pemungut (WAPU)</b>, dan baru dianggap "setor" setelah Bukti Setor Pajak (SSP)
                        dari WAPU diterima &amp; dicocokkan lewat jurnal manual (debit akun Utang PPN Keluaran, kredit clearing).
                    </p>
                </div>

                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                    <Breakdown title="PPN Keluaran" data={ppn_keluaran} />
                    <Breakdown title="PPN Masukan" data={ppn_masukan} />
                    <div className="card">
                        <p className="text-slate-300 text-sm font-medium mb-3">Estimasi Kurang / Lebih Bayar PPN</p>
                        <p className={`text-2xl font-bold mb-1 ${selisih >= 0 ? 'text-red-400' : 'text-emerald-400'}`}>{signed(selisih)}</p>
                        <p className="text-slate-500 text-xs">
                            {selisih >= 0
                                ? 'PPN Keluaran lebih besar dari PPN Masukan — indikasi kurang bayar (perlu disetor) sebelum SPT.'
                                : 'PPN Masukan lebih besar dari PPN Keluaran — indikasi lebih bayar / dapat dikompensasi.'}
                        </p>
                        <p className="text-slate-600 text-[11px] mt-3">PPN Keluaran − PPN Masukan, kumulatif s/d akhir tahun {year}. Kontrol dasar, bukan pengganti perhitungan SPT resmi.</p>
                    </div>
                </div>

                <div className="card">
                    <p className="text-slate-300 text-sm font-medium mb-1">PPN Dipungut WAPU — Belum Disetor / Dicocokkan</p>
                    <p className="text-slate-500 text-xs mb-3">Customer berstatus WAPU dengan saldo clearing (PPN Dipungut Wapu) belum nol — berarti SSP dari WAPU belum diterima/dicocokkan ke jurnal.</p>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Kode</th>
                                <th className="table-header">Customer WAPU</th>
                                <th className="table-header text-right">Saldo Clearing</th>
                            </tr>
                        </thead>
                        <tbody>
                            {wapu_outstanding.length === 0 && (
                                <tr><td colSpan={3} className="table-cell text-center text-slate-400">Tidak ada saldo clearing WAPU yang belum dicocokkan.</td></tr>
                            )}
                            {wapu_outstanding.map((w, i) => (
                                <tr key={i} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs">{w.code}</td>
                                    <td className="table-cell">{w.name}</td>
                                    <td className="table-cell text-right text-amber-300 font-medium">{signed(w.balance)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
