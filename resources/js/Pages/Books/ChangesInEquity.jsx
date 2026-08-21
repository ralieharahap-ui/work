import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

function Row({ label, value, strong, accent, sign }) {
    const v = Number(value) || 0;
    const shown = sign && v !== 0 ? `${v > 0 ? '+' : '−'} ${fmt(Math.abs(v))}` : fmt(v);
    return (
        <div className={`flex items-center justify-between px-3 py-2 ${strong ? 'border-t border-slate-600 mt-1' : 'border-b border-slate-700/40'}`}>
            <span className={strong ? 'font-semibold text-white' : 'text-slate-200'}>{label}</span>
            <span className={`tabular-nums ${strong ? 'font-bold' : ''} ${accent || (strong ? 'text-white' : 'text-slate-200')}`}>{shown}</span>
        </div>
    );
}

export default function ChangesInEquity({ period_from, period_to, opening, net_income, capital_in, dividend, other, closing }) {
    const [from, setFrom] = useState(period_from);
    const [to, setTo] = useState(period_to);
    const reload = () => router.get(route('books.changes-in-equity'), { from, to }, { preserveState: true });

    return (
        <>
            <Head title="Perubahan Ekuitas" />
            <AppLayout title="Laporan Perubahan Ekuitas">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div><label className="label">Dari</label>
                        <input type="date" className="input w-auto" value={from} onChange={(e) => setFrom(e.target.value)} onBlur={reload} /></div>
                    <div><label className="label">Sampai</label>
                        <input type="date" className="input w-auto" value={to} onChange={(e) => setTo(e.target.value)} onBlur={reload} /></div>
                    <div className="flex-1" />
                    <span className="text-xs text-slate-500 mb-2 hidden sm:inline">Disusun sesuai PSAK 1</span>
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="card max-w-2xl">
                    <Row label="Ekuitas Awal Periode" value={opening} strong accent="text-sky-300" />
                    <Row label="Laba / (Rugi) Tahun Berjalan" value={net_income} sign accent={net_income >= 0 ? 'text-emerald-300' : 'text-red-400'} />
                    <Row label="Setoran Modal" value={capital_in} sign accent="text-emerald-300" />
                    <Row label="Dividen" value={dividend} sign accent="text-red-400" />
                    {Math.round(other) !== 0 && <Row label="Penyesuaian Lain" value={other} sign accent="text-slate-300" />}
                    <div className="flex items-center justify-between px-4 py-3 mt-2 rounded-lg bg-emerald-900/30">
                        <span className="font-semibold text-white">Ekuitas Akhir Periode</span>
                        <span className="font-bold tabular-nums text-emerald-400">{fmt(closing)}</span>
                    </div>
                    <p className="text-slate-500 text-xs mt-3">Ekuitas akhir sama dengan total ekuitas pada Neraca per tanggal akhir periode.</p>
                </div>
            </AppLayout>
        </>
    );
}
