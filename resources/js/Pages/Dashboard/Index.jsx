import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import MapViewer from '@/Components/MapViewer.jsx';
import { MapDashboardIcon, PalmPlantationIcon, IndustryIcon, JettyIcon } from '@/Components/AppIcons';
import { CircleStackIcon, ExclamationTriangleIcon, ArrowRightIcon, BoltIcon } from '@heroicons/react/24/outline';

const fmtNum = (n) => new Intl.NumberFormat('id-ID').format(n ?? 0);

function StatCard({ label, value, unit, icon: Icon, color, sub, href, delay = 0 }) {
    const palette = {
        blue:    'from-blue-500/20 to-blue-600/5 ring-blue-500/20 text-blue-400',
        emerald: 'from-emerald-500/20 to-emerald-600/5 ring-emerald-500/20 text-emerald-400',
        amber:   'from-amber-500/20 to-amber-600/5 ring-amber-500/20 text-amber-400',
        red:     'from-red-500/20 to-red-600/5 ring-red-500/20 text-red-400',
        sky:     'from-sky-500/20 to-sky-600/5 ring-sky-500/20 text-sky-400',
    }[color];

    const inner = (
        <>
            <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ring-1 ring-inset flex items-center justify-center shrink-0
                             transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6 ${palette}`}>
                <Icon className="w-7 h-7" />
            </div>
            <div className="min-w-0">
                <p className="text-slate-400 text-xs font-medium">{label}</p>
                <p className="text-white text-2xl font-bold mt-0.5 tabular leading-none animate-count-up">
                    {value}
                    {unit && <span className="text-slate-500 text-sm font-medium ml-1">{unit}</span>}
                </p>
                {sub && <p className="text-slate-500 text-[11px] mt-1">{sub}</p>}
            </div>
        </>
    );

    const cls = 'group card card-hover flex items-center gap-4 animate-slide-up';
    const style = { animationDelay: `${delay}ms` };

    return href
        ? <Link href={href} style={style} className={cls}>{inner}</Link>
        : <div style={style} className={cls}>{inner}</div>;
}

export default function Dashboard({ palm_sources = [], unloading_points = [], jetty_points = [], pltu_locations = [], palm_summary = {} }) {
    const sorted = [...palm_sources].sort((a, b) => Number(b.stock_volume) - Number(a.stock_volume));
    const adaData = palm_sources.length > 0 || unloading_points.length > 0 || jetty_points.length > 0;

    return (
        <>
            <Head title="Dashboard" />
            <AppLayout title="Dashboard">
                {/* Statistik */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                    <StatCard label="Sumber Cangkang" value={fmtNum(palm_summary.source_count)} icon={PalmPlantationIcon}
                              color="emerald" href="/palm-oil-sources" delay={0} />
                    <StatCard label="Total Stok" value={fmtNum(palm_summary.total_volume)} unit="ton" icon={CircleStackIcon}
                              color="blue" delay={60} />
                    <StatCard label="Titik Bongkar" value={fmtNum(unloading_points.length)} icon={IndustryIcon}
                              color="red" href="/unloading-points" delay={120} />
                    <StatCard label="Titik Dermaga" value={fmtNum(jetty_points.length)} icon={JettyIcon}
                              color="sky" href="/jetty-points" delay={180} />
                    <StatCard label="PLTU Tujuan" value={fmtNum(pltu_locations.length)} icon={BoltIcon}
                              color="amber" delay={240} sub="Data referensi" />
                    <StatCard label="Stok Rendah" value={fmtNum(palm_summary.low_stock_count)} icon={ExclamationTriangleIcon}
                              color="red" delay={300}
                              sub={palm_summary.low_stock_count > 0 ? 'Perlu pengisian' : 'Semua aman'} />
                </div>

                {/* Peta */}
                <div className="card mb-6 animate-slide-up" style={{ animationDelay: '340ms' }}>
                    <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div>
                            <h2 className="text-white font-semibold text-sm flex items-center gap-2">
                                <MapDashboardIcon className="w-6 h-6" />
                                Peta Denah Rantai Pasok Cangkang Sawit
                            </h2>
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 mt-2 text-xs">
                                <span className="badge badge-green">🌴 {fmtNum(palm_summary.source_count)} Sumber</span>
                                <span className="badge badge-red">🏭 {fmtNum(unloading_points.length)} Titik Bongkar</span>
                                <span className="badge badge-blue">🚢 {fmtNum(jetty_points.length)} Dermaga</span>
                            </div>
                        </div>
                        <Link href="/palm-oil-sources" className="btn-ghost text-blue-400 hover:text-blue-300 text-sm group">
                            Kelola Sumber
                            <ArrowRightIcon className="w-4 h-4 transition-transform group-hover:translate-x-1" />
                        </Link>
                    </div>

                    {adaData ? (
                        <>
                            <MapViewer
                                sources={palm_sources}
                                unloadingPoints={unloading_points}
                                jettyPoints={jetty_points}
                                showRoutes
                                zoom={5}
                            />
                            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 text-xs text-slate-400">
                                <span className="inline-flex items-center gap-1.5">
                                    <PalmPlantationIcon className="w-5 h-5" /> Sumber cangkang (hijau)
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <IndustryIcon className="w-5 h-5" /> Titik bongkar / PLTU (merah)
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <JettyIcon className="w-5 h-5" /> Titik dermaga (biru)
                                </span>
                                <span className="text-slate-500">┈┈ Rute sumber → titik bongkar terdekat</span>
                                <span className="text-sky-400/90">💡 Klik titik mana pun untuk membuka detailnya</span>
                            </div>
                        </>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-16 text-slate-500">
                            <MapDashboardIcon className="w-14 h-14 mb-3 opacity-70 animate-float" />
                            <p className="text-sm">Belum ada data untuk ditampilkan di peta.</p>
                            <Link href="/palm-oil-sources/create" className="text-blue-400 hover:text-blue-300 text-sm mt-2">
                                + Tambah sumber pertama
                            </Link>
                        </div>
                    )}
                </div>

                {/* Ringkasan sumber */}
                <div className="card animate-slide-up" style={{ animationDelay: '420ms' }}>
                    <h2 className="section-title">
                        <CircleStackIcon className="w-[18px] h-[18px] text-slate-400" /> Ringkasan Stok per Sumber
                    </h2>
                    {sorted.length === 0 ? (
                        <p className="text-slate-500 text-sm py-6 text-center">Belum ada data.</p>
                    ) : (
                        <div className="overflow-x-auto -mx-2">
                            <table className="w-full md:min-w-[560px] table-stack">
                                <thead>
                                    <tr className="border-b border-slate-700/70">
                                        <th className="table-header">Nama Sumber</th>
                                        <th className="table-header">Lokasi</th>
                                        <th className="table-header text-right">Stok</th>
                                        <th className="table-header">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sorted.map((s, i) => {
                                        const low = Number(s.stock_volume) <= Number(s.stock_min);
                                        return (
                                            <tr key={s.id}
                                                style={{ animationDelay: `${460 + i * 45}ms` }}
                                                className="border-b border-slate-800/70 last:border-0 hover:bg-slate-700/20 transition-colors animate-fade-in">
                                                <td className="table-cell font-medium">
                                                    <Link href={`/palm-oil-sources/${s.id}`}
                                                          className="hover:text-emerald-300 transition-colors inline-flex items-center gap-2">
                                                        <span className="w-2 h-2 rounded-full bg-emerald-500 shrink-0" />
                                                        {s.name}
                                                    </Link>
                                                </td>
                                                <td data-label="Lokasi" className="table-cell text-slate-400">{s.city}, {s.province}</td>
                                                <td data-label="Stok" className={`table-cell text-right font-semibold tabular ${low ? 'text-red-400' : 'text-emerald-400'}`}>
                                                    {fmtNum(s.stock_volume)} <span className="text-slate-500 font-normal">{s.unit}</span>
                                                </td>
                                                <td data-label="Status" className="table-cell">
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
