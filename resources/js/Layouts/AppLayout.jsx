import { Link, usePage, router } from '@inertiajs/react';
import { ArrowRightOnRectangleIcon, XCircleIcon, Bars3Icon, XMarkIcon } from '@heroicons/react/24/outline';
import { useState, useEffect } from 'react';
import {
    MapDashboardIcon, PalmPlantationIcon, IndustryIcon, JettyIcon,
    CalculatorColorIcon, UsersColorIcon, TaskBoardIcon,
} from '@/Components/AppIcons';

const nav = [
    { label: 'Dashboard',        href: '/',                   icon: MapDashboardIcon,   perm: null },
    { label: 'Manajemen Tugas',  href: '/tasks',              icon: TaskBoardIcon,      perm: 'tasks.view' },
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
        <div className="fixed inset-x-0 top-0 z-[60] flex justify-center px-3 pt-[calc(env(safe-area-inset-top)+0.75rem)] pointer-events-none">
            <div
                className={`pointer-events-auto relative overflow-hidden w-full max-w-lg rounded-2xl shadow-2xl backdrop-blur-md
                    border ${isError
                        ? 'bg-red-950/90 border-red-500/40 shadow-red-900/40'
                        : 'bg-emerald-950/90 border-emerald-500/40 shadow-emerald-900/40'}
                    ${leaving ? 'animate-toast-out' : 'animate-toast-in'}`}
            >
                <div className="absolute inset-0 opacity-30 animate-shine bg-gradient-to-r from-transparent via-white/25 to-transparent" />

                <div className="relative flex items-start gap-3 p-3.5 sm:p-4 pr-10">
                    <div className={`shrink-0 w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center
                        ${isError ? 'bg-red-500/20 ring-1 ring-red-400/40' : 'bg-emerald-500/20 ring-1 ring-emerald-400/40 animate-pulse-ring'}`}>
                        {isError ? (
                            <XCircleIcon className="w-6 h-6 text-red-300" />
                        ) : (
                            <svg viewBox="0 0 52 52" className="w-6 h-6 sm:w-7 sm:h-7">
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
                        <p className="text-slate-100 text-[13px] sm:text-sm mt-0.5 leading-relaxed break-words">{msg}</p>
                    </div>

                    <button
                        onClick={() => setLeaving(true)}
                        className="absolute top-2 right-2 w-9 h-9 flex items-center justify-center text-white/50 hover:text-white transition-colors"
                        aria-label="Tutup"
                    >
                        <XMarkIcon className="w-5 h-5" />
                    </button>
                </div>

                <div className={`h-1 ${isError ? 'bg-red-400' : 'bg-emerald-400'} animate-progress origin-left`} />
            </div>
        </div>
    );
}

export default function AppLayout({ children, title }) {
    const { auth, pendingUsersCount, taskAlertsCount } = usePage().props;
    const user = auth?.user;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];
    const [drawerOpen, setDrawerOpen] = useState(false);

    // Tutup drawer setiap pindah halaman
    useEffect(() => router.on('navigate', () => setDrawerOpen(false)), []);

    // Kunci scroll body saat drawer terbuka (mobile)
    useEffect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    }, [drawerOpen]);

    // Tutup dengan tombol Esc
    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && setDrawerOpen(false);
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

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

    const SidebarContent = (
        <>
            <div className="px-4 sm:px-5 h-16 flex items-center gap-3 border-b border-slate-800/80 shrink-0">
                <img
                    src="/images/logo-gep.png"
                    alt="PT Geosys Energi Prima"
                    className="w-10 h-10 object-contain shrink-0 transition-transform duration-300 hover:scale-110 drop-shadow-[0_2px_6px_rgba(15,140,189,0.35)]"
                />
                <div className="min-w-0 flex-1">
                    <p className="text-white font-semibold text-sm leading-tight truncate">PT Geosys Energi Prima</p>
                    <p className="text-slate-500 text-[10px] leading-tight truncate">Database Sumber Biomassa by Ralie©2026</p>
                </div>
                <button
                    onClick={() => setDrawerOpen(false)}
                    className="lg:hidden w-9 h-9 -mr-1 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                    aria-label="Tutup menu"
                >
                    <XMarkIcon className="w-5 h-5" />
                </button>
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
                            className={`group relative flex items-center gap-3 px-3 min-h-[44px] rounded-lg text-sm font-medium
                                animate-slide-in-left transition-all duration-200 lg:hover:translate-x-1
                                ${active
                                    ? 'bg-blue-600/15 text-blue-200 shadow-sm shadow-blue-900/30'
                                    : 'text-slate-400 hover:text-white hover:bg-slate-800/70'}`}
                        >
                            {active && (
                                <span className="absolute left-0 top-1/2 -translate-y-1/2 h-6 w-1 rounded-r-full bg-gradient-to-b from-blue-400 to-sky-400 animate-grow-y" />
                            )}
                            <item.icon
                                className={`w-[22px] h-[22px] shrink-0 transition-transform duration-300
                                    lg:group-hover:scale-125 lg:group-hover:-rotate-6
                                    ${active ? 'scale-110 drop-shadow-[0_0_6px_rgba(56,189,248,0.5)]' : ''}`}
                            />
                            <span className="flex-1 truncate">{item.label}</span>
                            {item.href === '/admin/users' && pendingUsersCount > 0 && (
                                <span className="min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-slate-950 text-[10px] font-bold flex items-center justify-center animate-bounce-soft">
                                    {pendingUsersCount}
                                </span>
                            )}
                            {item.href === '/tasks' && taskAlertsCount > 0 && (
                                <span className="min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center animate-bounce-soft">
                                    {taskAlertsCount}
                                </span>
                            )}
                        </Link>
                    );
                })}
            </nav>

            <div className="px-3 py-3 border-t border-slate-800/80 shrink-0 pb-[calc(env(safe-area-inset-bottom)+0.75rem)]">
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
                    className="mt-1 w-full flex items-center gap-2 px-3 min-h-[44px] rounded-lg text-xs text-slate-500 hover:text-red-400 hover:bg-slate-800/70 transition-all duration-200"
                >
                    <ArrowRightOnRectangleIcon className="w-4 h-4" /> Keluar
                </Link>
            </div>
        </>
    );

    return (
        <div className="flex app-viewport bg-slate-900 overflow-hidden">
            {/* Sidebar tetap — laptop & monitor besar */}
            <aside className="hidden lg:flex w-64 xl:w-72 shrink-0 bg-slate-950/80 border-r border-slate-800 flex-col
                              pl-[env(safe-area-inset-left)]">
                {SidebarContent}
            </aside>

            {/* Drawer — HP & tablet */}
            <div className={`lg:hidden fixed inset-0 z-50 ${drawerOpen ? '' : 'pointer-events-none'}`}>
                <div
                    onClick={() => setDrawerOpen(false)}
                    className={`absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity duration-300
                                ${drawerOpen ? 'opacity-100' : 'opacity-0'}`}
                />
                <aside className={`absolute inset-y-0 left-0 w-[82vw] max-w-[19rem] bg-slate-950 border-r border-slate-800
                                   flex flex-col shadow-2xl transition-transform duration-300 ease-out
                                   pl-[env(safe-area-inset-left)]
                                   ${drawerOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                    {SidebarContent}
                </aside>
            </div>

            {/* Konten utama */}
            <div className="flex-1 flex flex-col overflow-hidden min-w-0">
                <header className="h-16 shrink-0 bg-slate-900/85 backdrop-blur border-b border-slate-800
                                   px-3 sm:px-5 lg:px-6 flex items-center gap-3 sticky top-0 z-10
                                   pt-[env(safe-area-inset-top)] pr-[calc(env(safe-area-inset-right)+0.75rem)]">
                    <button
                        onClick={() => setDrawerOpen(true)}
                        className="lg:hidden w-10 h-10 -ml-1 shrink-0 flex items-center justify-center rounded-lg
                                   text-slate-300 hover:text-white hover:bg-slate-800 transition-colors"
                        aria-label="Buka menu"
                    >
                        <Bars3Icon className="w-6 h-6" />
                    </button>

                    <h1 className="text-white font-semibold text-[15px] sm:text-base tracking-tight animate-fade-in truncate flex-1 min-w-0">
                        {title}
                    </h1>

                    <div className="flex items-center gap-2 shrink-0">
                        {roles[0] && (
                            <span className="badge badge-blue capitalize hidden sm:inline-flex">{roles[0].replace('_', ' ')}</span>
                        )}
                        {user?.division?.name && (
                            <span className="text-slate-400 text-xs hidden xl:inline">{user.division.name}</span>
                        )}
                    </div>
                </header>

                <main className="flex-1 overflow-y-auto overscroll-contain">
                    <div className="max-w-[1600px] mx-auto p-3 sm:p-5 lg:p-6 animate-fade-in
                                    pb-[calc(env(safe-area-inset-bottom)+1rem)]
                                    pl-[calc(env(safe-area-inset-left)+0.75rem)] pr-[calc(env(safe-area-inset-right)+0.75rem)]
                                    sm:pl-5 sm:pr-5 lg:pl-6 lg:pr-6">
                        {children}
                    </div>
                </main>
            </div>

            <Flash />
        </div>
    );
}
