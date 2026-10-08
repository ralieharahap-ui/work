import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => {
    const v = Math.round(Number(n) || 0);
    const s = new Intl.NumberFormat('id-ID').format(Math.abs(v));
    return v < 0 ? `(${s})` : s;
};

function Group({ label, rows, total }) {
    return (
        <>
            <tr><td colSpan={2} className="table-cell pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</td></tr>
            {rows.length === 0 ? (
                <tr><td colSpan={2} className="table-cell py-1 pl-5 text-xs text-slate-500">— nihil —</td></tr>
            ) : rows.map((a) => (
                <tr key={a.code} className="border-b border-slate-700/40">
                    <td className="table-cell pl-5">
                        <span className="font-mono text-xs text-slate-500 mr-2">{a.code}</span>{a.name}
                    </td>
                    <td className="table-cell text-right tabular">{fmt(a.amount)}</td>
                </tr>
            ))}
            <tr className="border-t border-slate-700">
                <td className="table-cell pl-5 font-medium">Jumlah {label}</td>
                <td className="table-cell text-right tabular font-semibold">{fmt(total)}</td>
            </tr>
        </>
    );
}

function TotalRow({ label, value, accent }) {
    return (
        <tr className="border-t-2 border-slate-600">
            <td className="table-cell font-bold text-white">{label}</td>
            <td className={`table-cell text-right tabular font-bold ${accent}`}>{fmt(value)}</td>
        </tr>
    );
}

export default function BalanceSheet({
    groups, assets = [], liabilities = [], equity = [], net_income = 0, prior_income = 0,
    total_assets, total_liab, total_equity, total_liab_equity, is_balanced, as_of,
}) {
    const [date, setDate] = useState(as_of);
    const assetGroups = groups?.assets ?? [{ label: 'Aset', rows: assets, total: total_assets }];
    const liabGroups  = groups?.liabilities ?? [{ label: 'Liabilitas', rows: liabilities, total: total_liab }];
    const year = (as_of || '').slice(0, 4);
    const equityRows = [
        ...equity,
        ...(Math.abs(prior_income) > 0.004 ? [{ code: '31301*', name: 'Saldo Laba Tahun-tahun Lalu (belum ditutup)', amount: prior_income }] : []),
        { code: '31302*', name: `${net_income >= 0 ? 'Laba' : 'Rugi'} Tahun Berjalan ${year}`, amount: net_income },
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
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="grid lg:grid-cols-2 gap-5">
                    <div className="card">
                        <h2 className="text-white font-semibold mb-1">ASET</h2>
                        <table className="w-full">
                            <tbody>
                                {assetGroups.map((g) => <Group key={g.label} {...g} />)}
                                <TotalRow label="JUMLAH ASET" value={total_assets} accent="text-blue-300" />
                            </tbody>
                        </table>
                    </div>

                    <div className="card">
                        <h2 className="text-white font-semibold mb-1">LIABILITAS & EKUITAS</h2>
                        <table className="w-full">
                            <tbody>
                                {liabGroups.map((g) => <Group key={g.label} {...g} />)}
                                <TotalRow label="Jumlah Liabilitas" value={total_liab} accent="text-amber-300" />
                                <Group label="Ekuitas" rows={equityRows} total={total_equity} />
                                <TotalRow label="Jumlah Ekuitas" value={total_equity} accent="text-emerald-300" />
                            </tbody>
                        </table>
                        <div className={`flex items-center justify-between px-4 py-3 mt-4 rounded-lg ${is_balanced ? 'bg-emerald-900/30' : 'bg-red-900/30'}`}>
                            <span className="font-semibold text-white">JUMLAH LIABILITAS & EKUITAS</span>
                            <span className={`font-bold tabular ${is_balanced ? 'text-emerald-400' : 'text-red-400'}`}>{fmt(total_liab_equity)}</span>
                        </div>
                        <p className="text-[11px] text-slate-500 mt-2">
                            * Dihitung otomatis dari akun laba rugi yang belum ditutup (belum ada jurnal penutup ke 31301/31302).
                        </p>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
