import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => {
    const v = Math.round(Number(n) || 0);
    const s = new Intl.NumberFormat('id-ID').format(Math.abs(v));
    return v < 0 ? `(${s})` : s;
};

function Section({ section, negative }) {
    if (!section) return null;
    return (
        <>
            <tr>
                <td colSpan={3} className="table-cell pt-4 pb-1 font-semibold text-white">{section.label}</td>
            </tr>
            {section.accounts.length === 0 ? (
                <tr><td colSpan={3} className="table-cell py-1 pl-6 text-xs text-slate-500">— tidak ada transaksi —</td></tr>
            ) : section.accounts.map((a) => (
                <tr key={a.code} className="border-b border-slate-700/40">
                    <td className="table-cell font-mono text-xs w-24 pl-6">{a.code}</td>
                    <td className="table-cell">{a.name}</td>
                    <td className="table-cell text-right tabular">{fmt(a.amount)}</td>
                </tr>
            ))}
            <tr className="border-t border-slate-700">
                <td colSpan={2} className="table-cell font-medium pl-6">Jumlah {section.label}</td>
                <td className="table-cell text-right tabular font-semibold">{fmt(negative ? -section.total : section.total)}</td>
            </tr>
        </>
    );
}

function Subtotal({ label, value, strong }) {
    const pos = value >= 0;
    return (
        <tr className={strong ? '' : 'bg-slate-900/60'}>
            <td colSpan={2} className={`table-cell ${strong ? 'text-base font-bold text-white' : 'font-semibold text-white'}`}>{label}</td>
            <td className={`table-cell text-right tabular font-bold ${pos ? 'text-emerald-400' : 'text-red-400'}`}>{fmt(value)}</td>
        </tr>
    );
}

export default function ProfitLoss({ sections = [], summary = {}, net, period_from, period_to }) {
    const [from, setFrom] = useState(period_from);
    const [to, setTo] = useState(period_to);

    const reload = () => router.get(route('books.profit-loss'), { from, to }, { preserveState: true });
    const s = Object.fromEntries(sections.map((x) => [x.key, x]));
    const labaBersih = summary.laba_bersih ?? net;

    return (
        <>
            <Head title="Laba/Rugi" />
            <AppLayout title="Laporan Laba Rugi">
                <div className="flex flex-wrap items-end gap-3 mb-4 print:hidden">
                    <div>
                        <label className="label">Dari</label>
                        <input type="date" className="input w-auto" value={from}
                            onChange={(e) => setFrom(e.target.value)} onBlur={reload} />
                    </div>
                    <div>
                        <label className="label">Sampai</label>
                        <input type="date" className="input w-auto" value={to}
                            onChange={(e) => setTo(e.target.value)} onBlur={reload} />
                    </div>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="card max-w-3xl">
                    <p className="text-xs text-slate-500 mb-2">
                        Penyajian bertahap mengacu PSAK 201. Periode {period_from} s/d {period_to}. Angka dalam kurung = pengurang.
                    </p>
                    <table className="w-full">
                        <tbody>
                            <Section section={s.pendapatan} />
                            <Section section={s.hpp} negative />
                            <Subtotal label="LABA BRUTO" value={summary.laba_bruto} />
                            <Section section={s.beban_operasional} negative />
                            <Subtotal label="LABA USAHA" value={summary.laba_usaha} />
                            <Section section={s.pendapatan_lain} />
                            <Section section={s.beban_lain} negative />
                            <Subtotal label="LABA SEBELUM PAJAK PENGHASILAN" value={summary.laba_sebelum_pajak} />
                            <Section section={s.pajak} negative />
                        </tbody>
                    </table>

                    <div className={`flex items-center justify-between px-4 py-3 mt-4 rounded-lg ${labaBersih >= 0 ? 'bg-emerald-900/30' : 'bg-red-900/30'}`}>
                        <span className="font-semibold text-white">{labaBersih >= 0 ? 'LABA BERSIH TAHUN BERJALAN' : 'RUGI BERSIH TAHUN BERJALAN'}</span>
                        <span className={`font-bold text-lg tabular ${labaBersih >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>{fmt(labaBersih)}</span>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
