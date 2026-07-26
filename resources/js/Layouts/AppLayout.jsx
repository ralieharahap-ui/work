import { Link, usePage } from '@inertiajs/react';
import {
    HomeIcon, MapPinIcon, TruckIcon, LifebuoyIcon, CalculatorIcon,
    ArrowRightOnRectangleIcon, CheckCircleIcon, XCircleIcon,
} from '@heroicons/react/24/outline';
import { useState, useEffect } from 'react';

const nav = [
    { label: 'Dashboard',        href: '/',                   icon: HomeIcon,       perm: null },
    { label: 'Sumber Cangkang',  href: '/palm-oil-sources',   icon: MapPinIcon,     perm: 'inventory.view' },
    { label: 'Titik Bongkar',    href: '/unloading-points',   icon: TruckIcon,      perm: 'inventory.view' },
    { label: 'Titik Dermaga',    href: '/jetty-points',       icon: LifebuoyIcon,   perm: 'inventory.view' },
    { label: 'Kalkulasi Proyek', href: '/project-calculator', icon: CalculatorIcon, perm: 'inventory.view' },
];

function Flash() {
    const { flash } = usePage().props;
    const [show, setShow] = useState(false);
    const msg = flash?.success || flash?.error;
    const isError = !!flash?.error;

    useEffect(() => {
        if (msg) {
            setShow(true);
            const t = setTimeout(() => setShow(false), 4000);
            return () => clearTimeout(t);
        }
    }, [msg]);

    if (!msg || !show) return null;

    return (
        <div className="fixed top-4 right-4 z-50 animate-slide-in-right">
            <div className={`flex items-center gap-3 text-sm text-white pl-4 pr-3 py-3 rounded-lg shadow-lg backdrop-blur
                ${isError ? 'bg-red-600/95 shadow-red-900/40' : 'bg-emerald-600/95 shadow-emerald-900/40'}`}>
                {isError ? <XCircleIcon className="w-5 h-5 shrink-0" /> : <CheckCircleIcon className="w-5 h-5 shrink-0" />}
                <span className="font-medium">{msg}</span>
                <button onClick={() => setShow(false)} className="text-white/70 hover:text-white transition-colors ml-1">✕</button>
            </div>
        </div>
    );
}

export default function AppLayout({ children, title }) {
    const { auth } = usePage().props;
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

    return (
        <div className="flex h-screen bg-slate-900 overflow-hidden">
            {/* Sidebar */}
            <aside className="w-64 shrink-0 bg-slate-950/80 border-r border-slate-800 flex flex-col">
                <div className="px-5 h-16 flex items-center gap-3 border-b border-slate-800/80">
                    <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow-sm shrink-0">
                        <span className="text-white font-bold text-sm">GEP</span>
                    </div>
                    <div className="min-w-0">
                        <p className="text-white font-semibold text-sm leading-tight truncate">PT Geosys Energi Prima</p>
                        <p className="text-slate-500 text-[11px] leading-tight">ERP System</p>
                    </div>
                </div>

                <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                    <p className="px-3 pb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-600">Menu</p>
                    {nav.filter(canSee).map((item) => {
                        const active = isActive(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`group relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150
                                    ${active
                                        ? 'bg-blue-600/15 text-blue-300'
                                        : 'text-slate-400 hover:text-white hover:bg-slate-800/70'}`}
                            >
                                {active && <span className="absolute left-0 top-1/2 -translate-y-1/2 h-5 w-0.5 rounded-full bg-blue-400" />}
                                <item.icon className={`w-[18px] h-[18px] shrink-0 transition-colors ${active ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300'}`} />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="px-3 py-3 border-t border-slate-800/80">
                    <div className="flex items-center gap-3 px-2 py-2">
                        <div className="w-9 h-9 rounded-full bg-slate-700 flex items-center justify-center shrink-0">
                            <span className="text-slate-200 text-xs font-semibold">{initials}</span>
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
                        className="mt-1 w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-slate-500 hover:text-red-400 hover:bg-slate-800/70 transition-colors"
                    >
                        <ArrowRightOnRectangleIcon className="w-4 h-4" /> Keluar
                    </Link>
                </div>
            </aside>

            {/* Main */}
            <div className="flex-1 flex flex-col overflow-hidden">
                <header className="h-16 shrink-0 bg-slate-900/80 backdrop-blur border-b border-slate-800 px-6 flex items-center justify-between sticky top-0 z-10">
                    <h1 className="text-white font-semibold text-base tracking-tight">{title}</h1>
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
