import { Link, usePage } from '@inertiajs/react';
import { ArrowRightOnRectangleIcon, XCircleIcon } from '@heroicons/react/24/outline';
import { useState, useEffect } from 'react';
import {
    MapDashboardIcon, PalmPlantationIcon, IndustryIcon, JettyIcon,
    CalculatorColorIcon, UsersColorIcon,
} from '@/Components/AppIcons';

const nav = [
    { label: 'Dashboard',        href: '/',                   icon: MapDashboardIcon,   perm: null },
    { label: 'Sumber Cangkang',  href: '/palm-oil-sources',   icon: PalmPlantationIcon, perm: 'inventory.view' },
    { label: 'Titik Bongkar',    href: '/unloading-points',   icon: IndustryIcon,       perm: 'inventory.view' },
    { label: 'Titik Dermaga',    href: '/jetty-points',       icon: JettyIcon,          perm: 'inventory.view' },
    { label: 'Kalkulasi Proyek', href: '/project-calculator', icon: CalculatorColorIcon, perm: 'inventory.view' },
    { label: 'Manajemen User',   href: '/admin/users',        icon: UsersColorIcon,     role: 'super_admin' },
];

/** Toast sukses/gagal dengan animasi: slide-in, checkmark tergambar, progress bar mundur. */
function Flash() {
    const { flash } = usePage().props;
    const [show, setShow] = useState(false);
    const [leaving, setLeaving] = useState(false);
    const msg = flash?.success || flash?.error;
    const isError = !!flash?.error;

    useEffect(() => {
        if (!msg) return;
        setShow(true);
        setLeaving(false);
        const tOut = setTimeout(() => setLeaving(true), 4600);
        const tHide = setTimeout(() => setShow(false), 5100);
        return () => { clearTimeout(tOut); clearTimeout(tHide); };
    }, [msg]);

    if (!msg || !show) return null;

    return (
        <div className="fixed inset-x-0 top-5 z-[60] flex justify-center px-4 pointer-events-none">
            <div
                className={`pointer-events-auto relative overflow-hidden max-w-lg w-full rounded-2xl shadow-2xl backdrop-blur-md
                    border ${isError
                        ? 'bg-red-950/90 border-red-500/40 shadow-red-900/40'
                        : 'bg-emerald-950/90 border-emerald-500/40 shadow-emerald-900/40'}
                    ${leaving ? 'animate-toast-out' : 'animate-toast-in'}`}
            >
                {/* kilau bergerak */}
                <div className="absolute inset-0 opacity-30 animate-shine bg-gradient-to-r from-transparent via-white/25 to-transparent" />

                <div className="relative flex items-start gap-3.5 p-4 pr-11">
                    <div className={`shrink-0 w-11 h-11 rounded-full flex items-center justify-center
                        ${isError ? 'bg-red-500/20 ring-1 ring-red-400/40' : 'bg-emerald-500/20 ring-1 ring-emerald-400/40 animate-pulse-ring'}`}>
                        {isError ? (
                            <XCircleIcon className="w-6 h-6 text-red-300" />
                        ) : (
                            <svg viewBox="0 0 52 52" className="w-7 h-7">
                                <circle cx="26" cy="26" r="23" fill="none" stroke="#34D399" strokeWidth="3"
                                        className="animate-draw-circle" />
                                <path d="M15 27l8 8 15-16" fill="none" stroke="#6EE7B7" strokeWidth="4"
                                      strokeLinecap="round" strokeLinejoin="round" className="animate-draw-check" />
                            </svg>
                        )}
                    </div>

                    <div className="min-w-0 pt-0.5">
                        <p className={`font-semibold text-sm ${isError ? 'text-red-200' : 'text-emerald-200'}`}>
                            {isError ? 'Terjadi Kesalahan' : 'Berhasil!'}
                        </p>
                        <p className="text-slate-100 text-sm mt-0.5 leading-relaxed">{msg}</p>
                    </div>

                    <button
                        onClick={() => setLeaving(true)}
                        className="absolute top-3 right-3 text-white/50 hover:text-white transition-colors text-lg leading-none"
                        aria-label="Tutup"
                    >
                        ✕
                    </button>
                </div>

                <div className={`h-1 ${isError ? 'bg-red-400' : 'bg-emerald-400'} animate-progress origin-left`} />
            </div>
        </div>
    );
}

export default function AppLayout({ children, title }) {
    const { auth, pendingUsersCount } = usePage().props;
    const user = auth?.user;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];

    const canSee = (item) => {
        if (item.role) return roles.includes(item.role);
        if (!item.perm) return true;
        return permissions.includes(item.perm);
    };

    const isActive = (href) => {
        const path = window.location.pathname;
        return href === '/' ? path === '/' : path.startsWith(href);
    };

    const initials = (user?.name ?? 'U')
        .split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();

    const visible = nav.filter(canSee);

    return (
        <div className="flex h-screen bg-slate-900 overflow-hidden">
            {/* Sidebar */}
            <aside className="w-64 shrink-0 bg-slate-950/80 border-r border-slate-800 flex flex-col">
                <div className="px-5 h-16 flex items-center gap-3 border-b border-slate-800/80">
                    <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow-sm shrink-0 transition-transform duration-300 hover:scale-110 hover:rotate-3">
                        <span className="text-white font-bold text-sm">GEP</span>
                    </div>
                    <div className="min-w-0">
                        <p className="text-white font-semibold text-sm leading-tight truncate">PT Geosys Energi Prima</p>
                        <p className="text-slate-500 text-[11px] leading-tight">ERP System</p>
                    </div>
                </div>

                <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                    <p className="px-3 pb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-600">Menu</p>
                    {visible.map((item, i) => {
                        const active = isActive(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                style={{ animationDelay: `${i * 55}ms` }}
                                className={`group relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                                    animate-slide-in-left transition-all duration-200 hover:translate-x-1
                                    ${active
                                        ? 'bg-blue-600/15 text-blue-200 shadow-sm shadow-blue-900/30'
                                        : 'text-slate-400 hover:text-white hover:bg-slate-800/70'}`}
                            >
                                {active && (
                                    <span className="absolute left-0 top-1/2 -translate-y-1/2 h-6 w-1 rounded-r-full bg-gradient-to-b from-blue-400 to-sky-400 animate-grow-y" />
                                )}
                                <item.icon
                                    className={`w-[22px] h-[22px] shrink-0 transition-transform duration-300
                                        group-hover:scale-125 group-hover:-rotate-6
                                        ${active ? 'scale-110 drop-shadow-[0_0_6px_rgba(56,189,248,0.5)]' : ''}`}
                                />
                                <span className="flex-1">{item.label}</span>
                                {item.href === '/admin/users' && pendingUsersCount > 0 && (
                                    <span className="min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-slate-950 text-[10px] font-bold flex items-center justify-center animate-bounce-soft">
                                        {pendingUsersCount}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </nav>

                <div className="px-3 py-3 border-t border-slate-800/80">
                    <div className="flex items-center gap-3 px-2 py-2">
                        <div className="w-9 h-9 rounded-full bg-gradient-to-br from-slate-600 to-slate-700 flex items-center justify-center shrink-0 ring-1 ring-slate-600">
                            <span className="text-slate-100 text-xs font-semibold">{initials}</span>
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-slate-200 text-xs font-medium truncate">{user?.name}</p>
                            <p className="text-slate-500 text-[11px] truncate">{user?.email}</p>
                        </div>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="mt-1 w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-slate-500 hover:text-red-400 hover:bg-slate-800/70 transition-all duration-200 hover:translate-x-1"
                    >
                        <ArrowRightOnRectangleIcon className="w-4 h-4" /> Keluar
                    </Link>
                </div>
            </aside>

            {/* Main */}
            <div className="flex-1 flex flex-col overflow-hidden">
                <header className="h-16 shrink-0 bg-slate-900/80 backdrop-blur border-b border-slate-800 px-6 flex items-center justify-between sticky top-0 z-10">
                    <h1 className="text-white font-semibold text-base tracking-tight animate-fade-in">{title}</h1>
                    <div className="flex items-center gap-2.5">
                        {roles[0] && (
                            <span className="badge badge-blue capitalize">{roles[0].replace('_', ' ')}</span>
                        )}
                        {user?.division?.name && (
                            <span className="text-slate-400 text-xs hidden sm:inline">{user.division.name}</span>
                        )}
                    </div>
                </header>

                <main className="flex-1 overflow-y-auto">
                    <div className="max-w-7xl mx-auto p-6 animate-fade-in">
                        {children}
                    </div>
                </main>
            </div>

            <Flash />
        </div>
    );
}
