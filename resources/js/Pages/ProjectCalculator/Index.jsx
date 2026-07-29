import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useMemo, useState } from 'react';
import {
    CalculatorIcon, CheckCircleIcon, ExclamationTriangleIcon, LightBulbIcon,
    BookmarkIcon, TrashIcon, ArrowUpTrayIcon,
} from '@heroicons/react/24/outline';

const PPN = 0.11;
const rp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
const fmtNum = (n) => new Intl.NumberFormat('id-ID').format(n || 0);
const num = (v) => (v === '' || v === null || isNaN(v)) ? 0 : Number(v);
const tgl = (d) => d ? new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

function Field({ label, value, onChange, placeholder, suffix = '/ton', hint }) {
    return (
        <div>
            <label className="label">{label}</label>
            <div className="relative">
                <input type="number" step="1" className="input pr-14" value={value}
                       onChange={(e) => onChange(e.target.value)} placeholder={placeholder} />
                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs">{suffix}</span>
            </div>
            {hint && <p className="text-slate-500 text-xs mt-1">{hint}</p>}
        </div>
    );
}

export default function ProjectCalculator({ sources = [], customers = [], jetties = [], scenarios = [] }) {
    const [f, setF] = useState({
        volume: 1000,
        price_cangkang: 1200000,
        transport: 150000,
        via_water: true,
        tongkang: 250000,
        handling: 30000,
        muat: 40000,
        bongkar: 50000,
        price_customer: 2000000,
        is_wapu: true,
        target_margin: 15,
    });
    const set = (k) => (v) => setF((s) => ({ ...s, [k]: v }));

    const [scenarioName, setScenarioName] = useState('');
    const [saving, setSaving] = useState(false);
    const [deleteId, setDeleteId] = useState(null);

    const pickSource = (id) => {
        const s = sources.find((x) => x.id === id);
        if (s && s.price > 0) set('price_cangkang')(String(s.price));
    };
    const pickCustomer = (id) => {
        const c = customers.find((x) => x.id === id);
        if (c && c.price > 0) set('price_customer')(String(c.price));
    };
    const pickJetty = (id) => {
        const j = jetties.find((x) => x.id === id);
        if (j && j.price > 0) set('handling')(String(num(f.handling) + num(j.price)));
    };

    const r = useMemo(() => {
        const vol = num(f.volume);
        const m = num(f.target_margin) / 100;

        // ── BIAYA (per ton) ────────────────────────────────────────────────
        // Cangkang kena PPN; beban logistik diasumsikan tanpa PPN.
        const cangkangDpp = num(f.price_cangkang);
        const cangkangPpn = cangkangDpp * PPN;                 // PPN Masukan
        const logistics = num(f.transport) + (f.via_water ? num(f.tongkang) : 0)
                        + num(f.handling) + num(f.muat) + num(f.bongkar);
        const costDpp = cangkangDpp + logistics;               // biaya sebelum PPN
        const costInclPpn = costDpp + cangkangPpn;             // biaya include PPN (kas keluar)

        // ── PENDAPATAN (per ton) ───────────────────────────────────────────
        const revDpp = num(f.price_customer);                  // DPP di faktur
        const revPpn = revDpp * PPN;                           // PPN Keluaran
        const revInclPpn = revDpp + revPpn;                    // nilai faktur

        // WAPU: customer hanya membayar DPP, PPN disetor sendiri ke negara.
        const wapu = !!f.is_wapu;
        const cashIn = wapu ? revDpp : revInclPpn;             // yang benar-benar diterima

        // ── MARGIN ─────────────────────────────────────────────────────────
        // Neto (ekonomis): PPN Masukan tetap dapat dikreditkan/direstitusi,
        // jadi margin sebenarnya dihitung atas dasar DPP.
        const marginNetPerTon = revDpp - costDpp;
        const marginNetPct = revDpp > 0 ? (marginNetPerTon / revDpp) * 100 : 0;

        // Kas: sebelum PPN Masukan direstitusi (relevan untuk modal kerja WAPU).
        const marginCashPerTon = cashIn - costInclPpn;
        const marginCashPct = cashIn > 0 ? (marginCashPerTon / cashIn) * 100 : 0;

        // Basis tampilan utama: WAPU → arus kas; reguler → nilai bruto faktur.
        const marginPerTon = wapu ? marginCashPerTon : (revInclPpn - costInclPpn);
        const basisRevenue = wapu ? cashIn : revInclPpn;
        const marginPct = basisRevenue > 0 ? (marginPerTon / basisRevenue) * 100 : 0;

        // ── REKOMENDASI (dasar DPP — margin ekonomis yang sesungguhnya) ─────
        const otherFixed = num(f.handling) + num(f.muat) + num(f.bongkar);
        const maxCangkangBase = revDpp * (1 - m) - logistics;              // harga beli maks (sebelum PPN)
        const maxTransportBudget = revDpp * (1 - m) - cangkangDpp - otherFixed;
        const minCustomerBase = m < 1 ? costDpp / (1 - m) : 0;             // harga jual min (sebelum PPN)
        // Agar margin KAS memenuhi target saat WAPU (PPN Masukan belum kembali)
        const minCustomerBaseCash = m < 1 ? costInclPpn / (1 - m) : 0;

        return {
            vol, wapu,
            cangkangDpp, cangkangPpn, logistics, costDpp, costInclPpn,
            revDpp, revPpn, revInclPpn, cashIn,
            ppnTertahan: cangkangPpn,                 // PPN Masukan menunggu restitusi
            marginNetPerTon, marginNetPct, marginCashPerTon, marginCashPct,
            marginPerTon, marginPct,
            totalCostDpp: costDpp * vol,
            totalCostInclPpn: costInclPpn * vol,
            totalRevDpp: revDpp * vol,
            totalRevPpn: revPpn * vol,
            totalRevInclPpn: revInclPpn * vol,
            totalCashIn: cashIn * vol,
            totalPpnTertahan: cangkangPpn * vol,
            totalMarginNet: marginNetPerTon * vol,
            totalMarginCash: marginCashPerTon * vol,
            // dipakai untuk penyimpanan skenario & ringkasan
            costPerTon: costInclPpn,
            revenuePerTon: wapu ? cashIn : revInclPpn,
            totalCost: costInclPpn * vol,
            totalRevenue: (wapu ? cashIn : revInclPpn) * vol,
            totalMargin: marginPerTon * vol,
            maxCangkangBase, maxTransportBudget, minCustomerBase, minCustomerBaseCash,
            currentTransport: num(f.transport) + (f.via_water ? num(f.tongkang) : 0),
            profit: marginPerTon >= 0,
            meetsTarget: marginNetPct >= num(f.target_margin),
            meetsTargetCash: marginCashPct >= num(f.target_margin),
        };
    }, [f]);

    const saveScenario = () => {
        if (!scenarioName.trim()) return;
        setSaving(true);
        router.post(route('project-calculator.store'), {
            name: scenarioName,
            volume: num(f.volume), price_cangkang: num(f.price_cangkang), transport: num(f.transport),
            via_water: f.via_water, tongkang: num(f.tongkang), handling: num(f.handling),
            muat: num(f.muat), bongkar: num(f.bongkar), price_customer: num(f.price_customer),
            is_wapu: !!f.is_wapu, target_margin: num(f.target_margin),
            total_cost: Math.round(r.totalCost), total_revenue: Math.round(r.totalRevenue),
            total_margin: Math.round(r.totalMargin), margin_pct: Number(r.marginPct.toFixed(2)),
        }, {
            preserveScroll: true,
            onFinish: () => { setSaving(false); setScenarioName(''); },
        });
    };

    const loadScenario = (s) => {
        setF({
            volume: Number(s.volume), price_cangkang: Number(s.price_cangkang), transport: Number(s.transport),
            via_water: !!s.via_water, tongkang: Number(s.tongkang), handling: Number(s.handling),
            muat: Number(s.muat), bongkar: Number(s.bongkar), price_customer: Number(s.price_customer),
            is_wapu: !!s.is_wapu, target_margin: Number(s.target_margin),
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const removeScenario = () => {
        if (deleteId) router.delete(route('project-calculator.destroy', deleteId), {
            preserveScroll: true, onSuccess: () => setDeleteId(null),
        });
    };

    // [label, nilai DPP, nilai include PPN] — logistik diasumsikan tanpa PPN
    const costRows = [
        ['Harga cangkang', r.cangkangDpp, r.cangkangDpp + r.cangkangPpn],
        ['Beban transportasi (darat)', num(f.transport), num(f.transport)],
        ...(f.via_water ? [['Pengiriman via tongkang', num(f.tongkang), num(f.tongkang)]] : []),
        ['Biaya handling', num(f.handling), num(f.handling)],
        ['Biaya muat', num(f.muat), num(f.muat)],
        ['Biaya bongkar', num(f.bongkar), num(f.bongkar)],
    ];

    return (
        <>
            <Head title="Kalkulasi Proyek Pengadaan" />
            <AppLayout title="Kalkulasi Proyek Pengadaan Cangkang">
                <p className="text-slate-400 text-sm mb-5 max-w-3xl">
                    Hitung <span className="text-white">cost &amp; benefit</span> proyek pengadaan pasokan cangkang: seluruh komponen biaya
                    (harga cangkang incl. PPN + transportasi + tongkang + handling + muat + bongkar) dibandingkan dengan harga yang dibayar customer,
                    lalu dapatkan <span className="text-white">rekomendasi harga</span> untuk margin optimal.
                </p>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* INPUT */}
                    <div className="space-y-6">
                        <div className="card">
                            <h3 className="section-title"><CalculatorIcon className="w-[18px] h-[18px] text-blue-400" /> Parameter Proyek</h3>

                            {(sources.length > 0 || customers.length > 0) && (
                                <div className="grid sm:grid-cols-3 gap-3 mb-5 p-3 rounded-lg bg-slate-900/40 border border-slate-700/70">
                                    <div>
                                        <label className="label">Isi dari Sumber</label>
                                        <select className="input" defaultValue="" onChange={(e) => pickSource(e.target.value)}>
                                            <option value="">— pilih —</option>
                                            {sources.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="label">Isi dari Customer</label>
                                        <select className="input" defaultValue="" onChange={(e) => pickCustomer(e.target.value)}>
                                            <option value="">— pilih —</option>
                                            {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="label">+ Biaya Dermaga</label>
                                        <select className="input" defaultValue="" onChange={(e) => pickJetty(e.target.value)}>
                                            <option value="">— pilih —</option>
                                            {jetties.map((j) => <option key={j.id} value={j.id}>{j.name}</option>)}
                                        </select>
                                    </div>
                                </div>
                            )}

                            <div className="space-y-4">
                                <Field label="Volume Pengadaan" value={f.volume} onChange={set('volume')} placeholder="1000" suffix="ton" />
                                <Field label="Harga Cangkang (sebelum PPN)" value={f.price_cangkang} onChange={set('price_cangkang')} placeholder="1200000"
                                       hint={`Include PPN 11%: ${rp(num(f.price_cangkang) * 1.11)} /ton`} />
                                <Field label="Beban Transportasi Darat" value={f.transport} onChange={set('transport')} placeholder="150000" />

                                <div className="rounded-lg border border-slate-700 bg-slate-900/40 p-3">
                                    <label className="flex items-center gap-2.5 text-sm text-slate-200 cursor-pointer select-none">
                                        <input type="checkbox" checked={f.via_water} onChange={(e) => set('via_water')(e.target.checked)}
                                               className="rounded border-slate-600 bg-slate-800 text-blue-500 focus:ring-blue-500" />
                                        🚢 Pengiriman melewati perairan (via tongkang)
                                    </label>
                                    {f.via_water && (
                                        <div className="mt-3">
                                            <Field label="Beban Pengiriman via Tongkang" value={f.tongkang} onChange={set('tongkang')} placeholder="250000" />
                                        </div>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <Field label="Handling" value={f.handling} onChange={set('handling')} placeholder="30000" suffix="/t" />
                                    <Field label="Muat" value={f.muat} onChange={set('muat')} placeholder="40000" suffix="/t" />
                                    <Field label="Bongkar" value={f.bongkar} onChange={set('bongkar')} placeholder="50000" suffix="/t" />
                                </div>

                                <Field label="Harga Jual ke Customer (sebelum PPN / DPP)" value={f.price_customer} onChange={set('price_customer')} placeholder="2000000"
                                       hint={`Nilai faktur incl. PPN 11%: ${rp(num(f.price_customer) * 1.11)} /ton`} />

                                <div className="rounded-lg border border-amber-700/40 bg-amber-500/5 p-3">
                                    <label className="flex items-start gap-2.5 text-sm text-slate-200 cursor-pointer select-none">
                                        <input type="checkbox" checked={f.is_wapu} onChange={(e) => set('is_wapu')(e.target.checked)}
                                               className="mt-0.5 rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" />
                                        <span>
                                            🏛️ Customer berstatus <span className="font-semibold text-amber-300">WAPU</span> (Wajib Pungut, mis. PLN)
                                        </span>
                                    </label>
                                    <p className="text-slate-400 text-xs mt-2 leading-relaxed">
                                        {f.is_wapu
                                            ? <>Perusahaan hanya menerima <span className="text-amber-300 font-medium">DPP {rp(num(f.price_customer))}/ton</span>.
                                                PPN {rp(num(f.price_customer) * PPN)}/ton dipungut &amp; disetor customer langsung ke negara.</>
                                            : <>Customer membayar penuh <span className="text-blue-300 font-medium">{rp(num(f.price_customer) * (1 + PPN))}/ton</span> (DPP + PPN),
                                                lalu perusahaan menyetor PPN-nya ke negara.</>}
                                    </p>
                                </div>

                                <div>
                                    <label className="label">Target Margin (%)</label>
                                    <input type="range" min="0" max="50" step="1" value={f.target_margin}
                                           onChange={(e) => set('target_margin')(e.target.value)} className="w-full accent-blue-500" />
                                    <div className="flex justify-between text-xs text-slate-400"><span>0%</span><span className="text-white font-semibold">{f.target_margin}%</span><span>50%</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* HASIL */}
                    <div className="space-y-6">
                        {/* Ringkasan margin */}
                        <div className={`card border-l-4 ${r.profit ? 'border-l-emerald-500' : 'border-l-red-500'}`}>
                            <h3 className="section-title">
                                {r.profit ? <CheckCircleIcon className="w-[18px] h-[18px] text-emerald-400" /> : <ExclamationTriangleIcon className="w-[18px] h-[18px] text-red-400" />}
                                Hasil Cost &amp; Benefit
                            </h3>
                            {r.wapu && (
                                <div className="mb-4 rounded-lg bg-amber-500/10 border border-amber-600/30 px-3 py-2 text-xs text-amber-200">
                                    🏛️ Mode <span className="font-semibold">WAPU</span> — PPN keluaran {rp(r.totalRevPpn)} tidak diterima perusahaan
                                    (disetor customer langsung ke negara).
                                </div>
                            )}

                            {/* Biaya — dipisah sebelum & include PPN */}
                            <div className="rounded-lg bg-slate-900/50 p-3 mb-3">
                                <p className="text-slate-300 text-xs font-semibold mb-2">Total Biaya ({fmtNum(r.vol)} ton)</p>
                                <div className="space-y-1.5 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-slate-400">Sebelum PPN (DPP)</span>
                                        <span className="text-slate-200 tabular">{rp(r.totalCostDpp)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-400">PPN Masukan 11% (cangkang)</span>
                                        <span className="text-amber-300 tabular">+ {rp(r.totalPpnTertahan)}</span>
                                    </div>
                                    <div className="flex justify-between border-t border-slate-700/70 pt-1.5 font-semibold">
                                        <span className="text-white">Include PPN (kas keluar)</span>
                                        <span className="text-red-400 tabular">{rp(r.totalCostInclPpn)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Pendapatan — dipisah DPP & PPN */}
                            <div className="rounded-lg bg-slate-900/50 p-3 mb-3">
                                <p className="text-slate-300 text-xs font-semibold mb-2">Total Pendapatan</p>
                                <div className="space-y-1.5 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-slate-400">DPP (sebelum PPN)</span>
                                        <span className={`tabular ${r.wapu ? 'text-emerald-300 font-semibold' : 'text-slate-200'}`}>{rp(r.totalRevDpp)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-400">
                                            PPN Keluaran 11%
                                            {r.wapu && <span className="text-amber-400/90"> — dipungut WAPU</span>}
                                        </span>
                                        <span className={`tabular ${r.wapu ? 'text-amber-300 line-through decoration-amber-500/60' : 'text-slate-200'}`}>
                                            {rp(r.totalRevPpn)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-t border-slate-700/70 pt-1.5">
                                        <span className="text-slate-400">Nilai faktur (incl. PPN)</span>
                                        <span className="text-slate-300 tabular">{rp(r.totalRevInclPpn)}</span>
                                    </div>
                                    <div className="flex justify-between font-semibold">
                                        <span className="text-white">Diterima perusahaan</span>
                                        <span className="text-blue-400 tabular">{rp(r.totalCashIn)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Margin — neto (ekonomis) & kas */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div className="rounded-lg bg-slate-900/50 p-3.5">
                                    <p className="text-slate-400 text-xs">Margin Neto (dasar DPP)</p>
                                    <p className={`text-xl font-bold tabular ${r.marginNetPerTon >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                                        {rp(r.totalMarginNet)}
                                    </p>
                                    <div className="flex items-center gap-2 mt-1.5">
                                        <span className={`badge ${r.meetsTarget ? 'badge-green' : r.marginNetPerTon >= 0 ? 'badge-amber' : 'badge-red'}`}>
                                            {r.marginNetPct.toFixed(1)}%
                                        </span>
                                        <span className="text-slate-500 text-[11px]">{rp(r.marginNetPerTon)}/ton</span>
                                    </div>
                                    <p className="text-slate-500 text-[11px] mt-1.5 leading-snug">
                                        Setelah PPN Masukan dikreditkan/direstitusi — margin usaha sesungguhnya.
                                    </p>
                                </div>

                                <div className="rounded-lg bg-slate-900/50 p-3.5">
                                    <p className="text-slate-400 text-xs">Margin Kas {r.wapu && <span className="text-amber-400/90">(WAPU)</span>}</p>
                                    <p className={`text-xl font-bold tabular ${r.marginCashPerTon >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                                        {rp(r.totalMarginCash)}
                                    </p>
                                    <div className="flex items-center gap-2 mt-1.5">
                                        <span className={`badge ${r.meetsTargetCash ? 'badge-green' : r.marginCashPerTon >= 0 ? 'badge-amber' : 'badge-red'}`}>
                                            {r.marginCashPct.toFixed(1)}%
                                        </span>
                                        <span className="text-slate-500 text-[11px]">{rp(r.marginCashPerTon)}/ton</span>
                                    </div>
                                    <p className="text-slate-500 text-[11px] mt-1.5 leading-snug">
                                        Diterima − kas keluar, sebelum PPN Masukan {rp(r.totalPpnTertahan)} kembali.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Rincian biaya per ton — dipisah sebelum & include PPN */}
                        <div className="card">
                            <h3 className="section-title">Rincian Biaya per Ton</h3>
                            <div className="overflow-x-auto -mx-1">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-700/70">
                                            <th className="text-left font-medium text-slate-400 text-xs pb-2">Komponen</th>
                                            <th className="text-right font-medium text-slate-400 text-xs pb-2 px-2">Sebelum PPN</th>
                                            <th className="text-right font-medium text-slate-400 text-xs pb-2">Incl. PPN 11%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {costRows.map(([label, dpp, incl]) => (
                                            <tr key={label} className="border-b border-slate-800/60 last:border-0">
                                                <td className="py-1.5 text-slate-400">{label}</td>
                                                <td className="py-1.5 px-2 text-right text-slate-200 tabular">{rp(dpp)}</td>
                                                <td className={`py-1.5 text-right tabular ${incl !== dpp ? 'text-amber-300' : 'text-slate-500'}`}>
                                                    {rp(incl)}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="border-t border-slate-700">
                                            <td className="pt-2 font-semibold text-white">Total Biaya / ton</td>
                                            <td className="pt-2 px-2 text-right font-semibold text-slate-100 tabular">{rp(r.costDpp)}</td>
                                            <td className="pt-2 text-right font-semibold text-red-400 tabular">{rp(r.costInclPpn)}</td>
                                        </tr>
                                        <tr>
                                            <td className="pt-1.5 text-slate-400">Harga customer / ton</td>
                                            <td className="pt-1.5 px-2 text-right text-slate-200 tabular">{rp(r.revDpp)}</td>
                                            <td className="pt-1.5 text-right text-slate-300 tabular">{rp(r.revInclPpn)}</td>
                                        </tr>
                                        <tr>
                                            <td className="pt-1.5 font-semibold text-white">
                                                Diterima / ton {r.wapu && <span className="text-amber-400/90 font-normal text-xs">(WAPU: DPP saja)</span>}
                                            </td>
                                            <td colSpan={2} className="pt-1.5 text-right font-semibold text-blue-400 tabular">{rp(r.cashIn)}</td>
                                        </tr>
                                        <tr>
                                            <td className="pt-1.5 font-semibold text-white">Margin neto / ton (dasar DPP)</td>
                                            <td colSpan={2} className={`pt-1.5 text-right font-semibold tabular ${r.marginNetPerTon >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                                                {rp(r.marginNetPerTon)}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p className="text-slate-500 text-[11px] mt-3 leading-snug">
                                Hanya harga cangkang yang dikenakan PPN; beban logistik diasumsikan tanpa PPN.
                                {r.wapu && ' PPN Masukan ' + rp(r.cangkangPpn) + '/ton menjadi kredit pajak yang direstitusi, bukan biaya permanen.'}
                            </p>
                        </div>

                        {/* Rekomendasi */}
                        <div className="card bg-gradient-to-br from-blue-950/40 to-slate-800/70 border-blue-800/40">
                            <h3 className="section-title border-blue-800/40"><LightBulbIcon className="w-[18px] h-[18px] text-amber-300" /> Rekomendasi Harga (target margin {f.target_margin}%)</h3>
                            <p className="text-slate-400 text-xs mb-3 -mt-1">
                                Dihitung atas dasar <span className="text-white">DPP (sebelum PPN)</span> — margin usaha sesungguhnya,
                                karena PPN Masukan dapat dikreditkan.
                            </p>
                            <div className="space-y-3 text-sm">
                                <Reco ok={r.maxCangkangBase >= num(f.price_cangkang)}
                                      label="Harga beli cangkang maksimal (sebelum PPN)"
                                      value={rp(Math.max(0, r.maxCangkangBase))}
                                      note={r.maxCangkangBase >= num(f.price_cangkang)
                                          ? `Harga beli saat ini ${rp(num(f.price_cangkang))} masih aman (setara ${rp(Math.max(0, r.maxCangkangBase) * (1 + PPN))} incl. PPN).`
                                          : `Turunkan dari ${rp(num(f.price_cangkang))} agar target tercapai.`} />
                                <Reco ok={r.maxTransportBudget >= r.currentTransport}
                                      label="Anggaran transportasi maksimal (darat + tongkang)"
                                      value={rp(Math.max(0, r.maxTransportBudget))}
                                      note={r.maxTransportBudget >= r.currentTransport ? `Saat ini ${rp(r.currentTransport)} — masih dalam anggaran.` : `Saat ini ${rp(r.currentTransport)} — melebihi anggaran.`} />
                                <Reco ok={num(f.price_customer) >= r.minCustomerBase}
                                      label="Harga jual minimal ke customer (sebelum PPN / DPP)"
                                      value={rp(Math.max(0, r.minCustomerBase))}
                                      note={`Nilai faktur ${rp(Math.max(0, r.minCustomerBase) * (1 + PPN))} incl. PPN. ` + (num(f.price_customer) >= r.minCustomerBase
                                          ? 'Harga jual saat ini memenuhi target.'
                                          : `Naikkan dari ${rp(num(f.price_customer))} agar target tercapai.`)} />

                                {r.wapu && (
                                    <Reco ok={num(f.price_customer) >= r.minCustomerBaseCash}
                                          label="Harga jual minimal agar target tercapai dari KAS (WAPU)"
                                          value={rp(Math.max(0, r.minCustomerBaseCash))}
                                          note={num(f.price_customer) >= r.minCustomerBaseCash
                                              ? 'Target margin tetap tercapai walau PPN Masukan belum direstitusi.'
                                              : `Karena PPN tidak diterima, kas hanya ${rp(r.cashIn)}/ton. Perlu ${rp(Math.max(0, r.minCustomerBaseCash))} agar target tercapai sebelum restitusi.`} />
                                )}
                            </div>

                            {r.wapu && (
                                <div className="mt-4 rounded-lg bg-amber-500/10 border border-amber-600/30 p-3 text-xs text-amber-200 leading-relaxed">
                                    🏛️ <span className="font-semibold">Dampak WAPU pada arus kas:</span> perusahaan menerima {rp(r.totalCashIn)} (DPP saja),
                                    sementara PPN keluaran {rp(r.totalRevPpn)} disetor customer langsung ke negara.
                                    PPN Masukan <span className="font-semibold">{rp(r.totalPpnTertahan)}</span> tetap dibayar di muka ke supplier
                                    dan baru kembali lewat restitusi — siapkan modal kerja sebesar itu.
                                </div>
                            )}

                            <div className={`mt-3 rounded-lg p-3 text-sm ${r.meetsTarget ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300'}`}>
                                {r.meetsTarget
                                    ? `✅ Skema ini SUDAH memenuhi target margin ${f.target_margin}% (margin neto aktual ${r.marginNetPct.toFixed(1)}%). Margin neto proyek ${rp(r.totalMarginNet)}${r.wapu ? `, margin kas ${rp(r.totalMarginCash)} (${r.marginCashPct.toFixed(1)}%)` : ''}.`
                                    : `⚠️ Margin neto aktual ${r.marginNetPct.toFixed(1)}% di bawah target ${f.target_margin}%. Sesuaikan salah satu: turunkan harga beli cangkang ke ${rp(Math.max(0, r.maxCangkangBase))}, tekan transportasi ke ${rp(Math.max(0, r.maxTransportBudget))}, atau naikkan harga jual ke ${rp(Math.max(0, r.minCustomerBase))} (DPP).`}
                            </div>
                        </div>

                        {/* Simpan Skenario */}
                        <div className="card">
                            <h3 className="section-title"><BookmarkIcon className="w-[18px] h-[18px] text-blue-400" /> Simpan Skenario ke Database</h3>
                            <div className="flex flex-col sm:flex-row gap-3">
                                <input className="input flex-1" value={scenarioName} onChange={(e) => setScenarioName(e.target.value)}
                                       placeholder="Nama skenario, mis. Riau → Tidore 1000t" />
                                <button onClick={saveScenario} disabled={saving || !scenarioName.trim()} className="btn-primary">
                                    <BookmarkIcon className="w-4 h-4" /> {saving ? 'Menyimpan...' : 'Simpan'}
                                </button>
                            </div>
                            <p className="text-slate-500 text-xs mt-2">
                                Menyimpan seluruh parameter &amp; hasil (margin {r.marginPct.toFixed(1)}% · {rp(r.totalMargin)}).
                            </p>
                        </div>
                    </div>
                </div>

                {/* Skenario Tersimpan */}
                <div className="card mt-6">
                    <h3 className="section-title"><BookmarkIcon className="w-[18px] h-[18px] text-slate-400" /> Skenario Tersimpan ({scenarios.length})</h3>
                    {scenarios.length === 0 ? (
                        <p className="text-slate-500 text-sm py-6 text-center">Belum ada skenario tersimpan. Isi parameter lalu klik “Simpan”.</p>
                    ) : (
                        <div className="overflow-x-auto -mx-2">
                            <table className="w-full md:min-w-[860px] table-stack">
                                <thead>
                                    <tr className="border-b border-slate-700/70">
                                        <th className="table-header">Nama Skenario</th>
                                        <th className="table-header text-right">Volume</th>
                                        <th className="table-header text-right">Total Biaya</th>
                                        <th className="table-header text-right">Pendapatan</th>
                                        <th className="table-header text-right">Margin</th>
                                        <th className="table-header">Dibuat</th>
                                        <th className="table-header text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {scenarios.map((s) => (
                                        <tr key={s.id} className="border-b border-slate-800/70 last:border-0 hover:bg-slate-700/20 transition-colors">
                                            <td className="table-cell font-medium text-white">
                                                {s.name}
                                                {s.is_wapu && <span className="badge badge-amber ml-2 text-[10px]">WAPU</span>}
                                                {s.user && <p className="text-slate-500 text-xs font-normal">oleh {s.user.name}</p>}
                                            </td>
                                            <td data-label="Volume" className="table-cell text-right tabular text-slate-300">{fmtNum(s.volume)} t</td>
                                            <td data-label="Total Biaya" className="table-cell text-right tabular text-red-400">{rp(s.total_cost)}</td>
                                            <td data-label="Pendapatan" className="table-cell text-right tabular text-blue-400">{rp(s.total_revenue)}</td>
                                            <td data-label="Margin" className="table-cell text-right">
                                                <span className={`badge tabular ${Number(s.margin_pct) >= Number(s.target_margin) ? 'badge-green' : Number(s.total_margin) >= 0 ? 'badge-amber' : 'badge-red'}`}>
                                                    {rp(s.total_margin)} · {Number(s.margin_pct).toFixed(1)}%
                                                </span>
                                            </td>
                                            <td data-label="Dibuat" className="table-cell text-slate-400 text-xs whitespace-nowrap">{tgl(s.created_at)}</td>
                                            <td data-label="Aksi" className="table-cell">
                                                <div className="flex items-center justify-end gap-1">
                                                    <button onClick={() => loadScenario(s)} className="p-1.5 rounded-md text-slate-400 hover:text-blue-300 hover:bg-slate-700/50 transition-colors" title="Muat ke kalkulator">
                                                        <ArrowUpTrayIcon className="w-4 h-4 rotate-180" />
                                                    </button>
                                                    <button onClick={() => setDeleteId(s.id)} className="p-1.5 rounded-md text-slate-400 hover:text-red-400 hover:bg-slate-700/50 transition-colors" title="Hapus">
                                                        <TrashIcon className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {deleteId && (
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in" onClick={() => setDeleteId(null)}>
                        <div className="card w-full max-w-sm" onClick={(e) => e.stopPropagation()}>
                            <h2 className="text-white font-semibold">Hapus Skenario?</h2>
                            <p className="text-slate-400 text-sm mt-1">Skenario ini akan dihapus permanen.</p>
                            <div className="flex gap-3 pt-5">
                                <button onClick={removeScenario} className="btn-danger flex-1">Ya, Hapus</button>
                                <button onClick={() => setDeleteId(null)} className="btn-secondary flex-1">Batal</button>
                            </div>
                        </div>
                    </div>
                )}
            </AppLayout>
        </>
    );
}

function Reco({ ok, label, value, note }) {
    return (
        <div className="flex items-start gap-3">
            <span className={`mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-xs shrink-0 ${ok ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}`}>
                {ok ? '✓' : '!'}
            </span>
            <div>
                <p className="text-slate-300">{label}</p>
                <p className="text-white font-semibold tabular">{value} <span className="text-slate-500 font-normal text-xs">/ton</span></p>
                <p className="text-slate-500 text-xs">{note}</p>
            </div>
        </div>
    );
}
