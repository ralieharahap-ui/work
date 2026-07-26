import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import MapViewer from '@/Components/MapViewer.jsx';
import {
    MapPinIcon, CircleStackIcon, BoltIcon, ExclamationTriangleIcon, ArrowRightIcon,
    TruckIcon, LifebuoyIcon,
} from '@heroicons/react/24/outline';

const fmtNum = (n) => new Intl.NumberFormat('id-ID').format(n ?? 0);

function StatCard({ label, value, unit, icon: Icon, color, sub }) {
    const palette = {
        blue:    'from-blue-500/20 to-blue-600/5 text-blue-400 ring-blue-500/20',
        emerald: 'from-emerald-500/20 to-emerald-600/5 text-emerald-400 ring-emerald-500/20',
        amber:   'from-amber-500/20 to-amber-600/5 text-amber-400 ring-amber-500/20',
        red:     'from-red-500/20 to-red-600/5 text-red-400 ring-red-500/20',
        sky:     'from-sky-500/20 to-sky-600/5 text-sky-400 ring-sky-500/20',
    }[color];

    return (
        <div className="card card-hover flex items-center gap-4">
            <div className={`w-11 h-11 rounded-xl bg-gradient-to-br ring-1 ring-inset flex items-center justify-center shrink-0 ${palette}`}>
                <Icon className="w-5 h-5" />
            </div>
            <div className="min-w-0">
                <p className="text-slate-400 text-xs font-medium">{label}</p>
                <p className="text-white text-2xl font-bold mt-0.5 tabular leading-none">
                    {value}
                    {unit && <span className="text-slate-500 text-sm font-medium ml-1">{unit}</span>}
                </p>
                {sub && <p className="text-slate-500 text-[11px] mt-1">{sub}</p>}
            </div>
        </div>
    );
}

export default function Dashboard({ palm_sources = [], unloading_points = [], jetty_points = [], pltu_locations = [], palm_summary = {} }) {
    const sorted = [...palm_sources].sort((a, b) => Number(b.stock_volume) - Number(a.stock_volume));

    return (
        <>
            <Head title="Dashboard" />
            <AppLayout title="Dashboard">
                {/* Statistik */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <StatCard label="Total Sumber"     value={fmtNum(palm_summary.source_count)}   icon={MapPinIcon}       color="emerald" />
                    <StatCard label="Total Stok"       value={fmtNum(palm_summary.total_volume)} unit="ton" icon={CircleStackIcon} color="blue" />
                    <StatCard label="Titik Bongkar"    value={fmtNum(unloading_points.length)}     icon={TruckIcon}        color="red" />
                    <StatCard label="Titik Dermaga"    value={fmtNum(jetty_points.length)}         icon={LifebuoyIcon}     color="sky" />
                    <StatCard label="PLTU Tujuan"      value={fmtNum(pltu_locations.length)}       icon={BoltIcon}         color="amber" />
                </div>

                {/* Peta */}
                <div className="card mb-6">
                    <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div>
                            <h2 className="text-white font-semibold text-sm flex items-center gap-2">
                                <MapPinIcon className="w-[18px] h-[18px] text-emerald-400" />
                                Peta Denah Rantai Pasok Cangkang Sawit
                            </h2>
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 mt-2 text-xs text-slate-400">
                                <span className="badge badge-green">🟢 {fmtNum(palm_summary.source_count)} Sumber</span>
                                <span className="badge badge-red">🔴 {fmtNum(unloading_points.length)} Titik Bongkar</span>
                                <span className="badge badge-blue">🔵 {fmtNum(jetty_points.length)} Dermaga</span>
                                <span className="badge badge-amber">🟠 {fmtNum(pltu_locations.length)} PLTU</span>
                            </div>
                        </div>
                        <Link href="/palm-oil-sources" className="btn-ghost text-blue-400 hover:text-blue-300 text-sm">
                            Kelola Sumber <ArrowRightIcon className="w-4 h-4" />
                        </Link>
                    </div>

                    {palm_sources.length > 0 || unloading_points.length > 0 || jetty_points.length > 0 || pltu_locations.length > 0 ? (
                        <>
                        <MapViewer sources={palm_sources} unloadingPoints={unloading_points} jettyPoints={jetty_points} pltuLocations={pltu_locations} showRoutes zoom={5} />
                        <p className="text-slate-500 text-xs mt-3 flex flex-wrap gap-x-4 gap-y-1">
                            <span>🟢 Sumber cangkang</span>
                            <span>🔴 Titik bongkar (customer)</span>
                            <span>🚢 Titik dermaga</span>
                            <span>🟠 PLTU</span>
                            <span>┈┈ Rute sumber → titik bongkar terdekat</span>
                        </p>
                        </>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-16 text-slate-500">
                            <MapPinIcon className="w-10 h-10 mb-3 opacity-60" />
                            <p className="text-sm">Belum ada data sumber cangkang sawit.</p>
                            <Link href="/palm-oil-sources/create" className="text-blue-400 hover:text-blue-300 text-sm mt-2">
                                + Tambah sumber pertama
                            </Link>
                        </div>
                    )}
                </div>

                {/* Ringkasan sumber */}
                <div className="card">
                    <h2 className="section-title">
                        <CircleStackIcon className="w-[18px] h-[18px] text-slate-400" /> Ringkasan Stok per Sumber
                    </h2>
                    {sorted.length === 0 ? (
                        <p className="text-slate-500 text-sm py-6 text-center">Belum ada data.</p>
                    ) : (
                        <div className="overflow-x-auto -mx-2">
                            <table className="w-full min-w-[560px]">
                                <thead>
                                    <tr className="border-b border-slate-700/70">
                                        <th className="table-header">Nama Sumber</th>
                                        <th className="table-header">Lokasi</th>
                                        <th className="table-header text-right">Stok</th>
                                        <th className="table-header">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sorted.map((s) => {
                                        const low = Number(s.stock_volume) <= Number(s.stock_min);
                                        return (
                                            <tr key={s.id} className="border-b border-slate-800/70 last:border-0 hover:bg-slate-700/20 transition-colors">
                                                <td className="table-cell font-medium">
                                                    <Link href={`/palm-oil-sources/${s.id}`} className="hover:text-blue-300 transition-colors">{s.name}</Link>
                                                </td>
                                                <td className="table-cell text-slate-400">{s.city}, {s.province}</td>
                                                <td className={`table-cell text-right font-semibold tabular ${low ? 'text-red-400' : 'text-emerald-400'}`}>
                                                    {fmtNum(s.stock_volume)} <span className="text-slate-500 font-normal">{s.unit}</span>
                                                </td>
                                                <td className="table-cell">
                                                    <span className={`badge ${low ? 'badge-red' : 'badge-green'}`}>
                                                        {low ? 'Stok Rendah' : 'Aman'}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </AppLayout>
        </>
    );
}
