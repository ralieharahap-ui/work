import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRightIcon, HomeIcon } from '@heroicons/react/24/outline';
import AppLayout from '@/Layouts/AppLayout';
import { nav, GROUP_META, LOOSE_META, THEMES, canSee } from '@/navigation';

function ModuleCard({ name, meta, items, delay }) {
    const t = THEMES[meta.theme];
    const Icon = meta.icon;
    return (
        <div className={`rounded-2xl bg-[#fff] border ${t.border} shadow-[0_1px_2px_rgb(16_24_40_/_0.04),0_6px_18px_rgb(16_24_40_/_0.05)]
                         overflow-hidden flex flex-col animate-slide-up`} style={{ animationDelay: `${delay}ms` }}>
            <div className={`flex items-center gap-3.5 px-4 py-4 bg-gradient-to-br ${t.head}`}>
                <span className={`w-12 h-12 rounded-xl flex items-center justify-center shrink-0 ${t.solid} text-[#fff] ${t.glow}`}>
                    <Icon className="w-6 h-6" />
                </span>
                <div className="min-w-0">
                    <h2 className="text-slate-100 font-bold text-[17px] leading-tight">{name}</h2>
                    <p className="text-slate-400 text-xs mt-0.5 leading-snug">{meta.desc}</p>
                </div>
            </div>
            <ul className="px-2 py-2 flex-1">
                {items.map((it) => (
                    <li key={it.href}>
                        <Link href={it.href}
                            className={`group flex items-center gap-3 px-2.5 min-h-[40px] rounded-lg text-sm text-slate-200 ${t.hover} transition-colors`}>
                            <it.icon className={`w-[18px] h-[18px] shrink-0 ${t.text}`} />
                            <span className="flex-1 truncate">{it.label}</span>
                            <ChevronRightIcon className="w-4 h-4 text-slate-500 transition-transform group-hover:translate-x-0.5" />
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function ModuleStrip({ item, meta, delay }) {
    const t = THEMES[meta.theme];
    const Icon = meta.icon;
    return (
        <Link href={item.href}
            className={`group flex items-center gap-4 rounded-2xl bg-[#fff] border ${t.border} px-4 py-3.5
                        shadow-[0_1px_2px_rgb(16_24_40_/_0.04),0_6px_18px_rgb(16_24_40_/_0.05)]
                        hover:-translate-y-0.5 hover:shadow-[0_12px_26px_-10px_rgb(16_24_40_/_0.2)] transition-all animate-slide-up`}
            style={{ animationDelay: `${delay}ms` }}>
            <span className={`w-12 h-12 rounded-xl flex items-center justify-center shrink-0 ${t.soft} ${t.text}`}>
                <Icon className="w-6 h-6" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-slate-100 font-bold text-[15px]">{item.label}</span>
                <span className="block text-slate-400 text-xs mt-0.5">{meta.desc}</span>
            </span>
            <ChevronRightIcon className="w-5 h-5 text-slate-500 transition-transform group-hover:translate-x-1" />
        </Link>
    );
}

export default function MenuIndex() {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];
    const visible = nav.filter((it) => canSee(it, permissions, roles));

    const groups = Object.entries(GROUP_META)
        .map(([name, meta]) => ({ name, meta, items: visible.filter((it) => it.group === name) }))
        .filter((g) => g.items.length > 0);
    const loose = visible.filter((it) => LOOSE_META[it.href]);

    return (
        <>
            <Head title="Menu Aplikasi" />
            <AppLayout title="Menu Aplikasi">
                <div className="flex flex-col xl:flex-row xl:items-stretch gap-5 mb-6">
                    <div className="flex-1 min-w-0">
                        <nav className="flex items-center gap-2 text-sm text-slate-400 mb-3" aria-label="Breadcrumb">
                            <Link href="/" className="inline-flex items-center gap-1.5 hover:text-brand-600">
                                <HomeIcon className="w-[18px] h-[18px] text-brand-600" /> Beranda
                            </Link>
                            <ChevronRightIcon className="w-3.5 h-3.5" />
                            <span className="text-slate-300">Menu Aplikasi</span>
                        </nav>
                        <h1 className="text-slate-100 font-extrabold text-[30px] sm:text-[38px] leading-tight tracking-tight">Menu Aplikasi ERP</h1>
                        <p className="text-slate-400 mt-1.5">Kelola seluruh proses bisnis PT Geosys Energi Prima secara terintegrasi.</p>
                    </div>

                    <div className="relative overflow-hidden rounded-2xl xl:w-[460px] min-h-[120px] bg-gradient-to-r from-[#0b3d5c] via-[#0f6e86] to-[#1d8f5c] text-white">
                        <svg viewBox="0 0 460 130" preserveAspectRatio="xMaxYMax slice" className="absolute inset-0 w-full h-full" aria-hidden="true">
                            <g opacity="0.5">
                                {[250, 278, 316, 348, 386].map((x, i) => (
                                    <g key={x}>
                                        <rect x={x} y={26 + (i % 3) * 10} width="10" height={104 - (i % 3) * 10} fill="#d8e9f2" />
                                        <rect x={x} y={36 + (i % 3) * 10} width="10" height="4" fill="#e05252" />
                                    </g>
                                ))}
                                <rect x="236" y="84" width="200" height="46" fill="#a8c9da" />
                            </g>
                            <path d="M210 130 C260 98 300 92 340 98 C390 104 420 86 460 90 L460 130Z" fill="#7a5532" />
                            <path d="M280 130 C320 110 370 108 410 114 C430 117 445 112 460 115 L460 130Z" fill="#5b3d22" />
                            {Array.from({ length: 22 }).map((_, i) => (
                                <circle key={i} cx={290 + ((i * 29) % 165)} cy={112 + ((i * 7) % 16)} r={1.5 + (i % 3)} fill="#a87b4f" />
                            ))}
                        </svg>
                        <div className="relative z-10 h-full flex items-center px-6 py-5">
                            <div className="border-l-4 border-[#34d399] pl-4">
                                <p className="font-bold text-xl leading-snug drop-shadow">Energi Bersih<br />Untuk Masa Depan</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
                    {groups.map((g, i) => (
                        <ModuleCard key={g.name} name={g.name} meta={g.meta} items={g.items} delay={i * 60} />
                    ))}
                </div>

                {loose.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        {loose.map((it, i) => (
                            <ModuleStrip key={it.href} item={it} meta={LOOSE_META[it.href]} delay={300 + i * 60} />
                        ))}
                    </div>
                )}

                {groups.length === 0 && loose.length === 0 && (
                    <p className="text-slate-500 text-sm py-10 text-center">Belum ada modul yang dapat Anda akses. Hubungi Super Admin.</p>
                )}
            </AppLayout>
        </>
    );
}
