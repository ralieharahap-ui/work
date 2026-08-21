import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

/** Sub-kelompok PSAK: judul + baris akun + subtotal. */
function SubGroup({ title, rows, total }) {
    return (
        <div className="mb-3">
            <p className="text-slate-300 font-medium text-sm mb-1">{title}</p>
            <table className="w-full">
                <tbody>
                    {rows.length === 0 && (
                        <tr><td className="px-3 py-1.5 text-slate-500 text-sm italic">— tidak ada saldo —</td></tr>
                    )}
                    {rows.map((a) => (
                        <tr key={a.code} className="border-b border-slate-700/40">
                            <td className="px-3 py-1.5 text-sm text-slate-200">
                                <span className="font-mono text-xs text-slate-500 mr-2">{a.code}</span>{a.name}
                            </td>
                            <td className="px-3 py-1.5 text-sm text-slate-200 text-right tabular-nums">{fmt(a.amount)}</td>
                        </tr>
                    ))}
                    <tr className="border-t border-slate-600/70">
                        <td className="px-3 py-1.5 text-sm text-slate-300">Subtotal {title}</td>
                        <td className="px-3 py-1.5 text-sm font-semibold text-right tabular-nums text-slate-100">{fmt(total)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

function GrandTotal({ label, value, accent }) {
    return (
        <div className="flex items-center justify-between px-4 py-2.5 mt-1 rounded-lg bg-slate-800/70 border border-slate-700">
            <span className="font-semibold text-white">{label}</span>
            <span className={`font-bold tabular-nums ${accent}`}>{fmt(value)}</span>
        </div>
    );
}

export default function BalanceSheet({
    asset_current, asset_noncurrent, liab_current, liab_noncurrent, equity, net_income,
    total_asset_current, total_asset_noncurrent, total_assets,
    total_liab_current, total_liab_noncurrent, total_liab,
    total_equity, total_liab_equity, is_balanced, as_of,
}) {
    const [date, setDate] = useState(as_of);

    const equityRows = [
        ...equity,
        { code: '—', name: net_income >= 0 ? 'Laba Tahun Berjalan' : 'Rugi Tahun Berjalan', amount: net_income },
    ];

    return (
        <>
            <Head title="Neraca" />
            <AppLayout title="Laporan Posisi Keuangan (Neraca)">
                <div className="flex flex-wrap items-center gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Per Tanggal</label>
                        <input type="date" className="input w-auto" value={date}
                            onChange={(e) => setDate(e.target.value)}
                            onBlur={() => router.get(route('books.balance-sheet'), { as_of: date }, { preserveState: true })} />
                    </div>
                    {is_balanced
                        ? <span className="badge badge-green mt-5">✓ Seimbang</span>
                        : <span className="badge badge-red mt-5">✗ Tidak Seimbang</span>}
                    <div className="flex-1" />
                    <span className="text-xs text-slate-500 mt-5 hidden sm:inline">Disusun sesuai PSAK 1</span>
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="grid lg:grid-cols-2 gap-5 items-start">
                    {/* ASET */}
                    <div className="card">
                        <h2 className="text-white font-semibold mb-3 pb-2 border-b border-slate-700">ASET</h2>
                        <SubGroup title="Aset Lancar" rows={asset_current} total={total_asset_current} />
                        <SubGroup title="Aset Tidak Lancar" rows={asset_noncurrent} total={total_asset_noncurrent} />
                        <GrandTotal label="TOTAL ASET" value={total_assets} accent="text-blue-300" />
                    </div>

                    {/* LIABILITAS & EKUITAS */}
                    <div className="card">
                        <h2 className="text-white font-semibold mb-3 pb-2 border-b border-slate-700">LIABILITAS &amp; EKUITAS</h2>
                        <SubGroup title="Liabilitas Jangka Pendek" rows={liab_current} total={total_liab_current} />
                        <SubGroup title="Liabilitas Jangka Panjang" rows={liab_noncurrent} total={total_liab_noncurrent} />
                        <GrandTotal label="Total Liabilitas" value={total_liab} accent="text-amber-300" />
                        <div className="mt-3" />
                        <SubGroup title="Ekuitas" rows={equityRows} total={total_equity} />
                        <GrandTotal label="Total Ekuitas" value={total_equity} accent="text-emerald-300" />
                        <div className={`flex items-center justify-between px-4 py-3 mt-2 rounded-lg ${is_balanced ? 'bg-emerald-900/30' : 'bg-red-900/30'}`}>
                            <span className="font-semibold text-white">TOTAL LIABILITAS &amp; EKUITAS</span>
                            <span className={`font-bold tabular-nums ${is_balanced ? 'text-emerald-400' : 'text-red-400'}`}>{fmt(total_liab_equity)}</span>
                        </div>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
