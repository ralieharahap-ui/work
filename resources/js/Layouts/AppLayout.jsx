import { Link, usePage, router } from '@inertiajs/react';
import {
    ArrowRightOnRectangleIcon, XCircleIcon, Bars3Icon, XMarkIcon, ChevronDownIcon, ChevronRightIcon,
    MagnifyingGlassIcon, BellIcon,
} from '@heroicons/react/24/outline';
import { useState, useEffect, useRef, useMemo } from 'react';
import { UsersColorIcon } from '@/Components/AppIcons';
import { nav, GROUP_META, LOOSE_META, SECTIONS, THEMES, canSee as canSeeItem, themeOf } from '@/navigation';

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
                className={`pointer-events-auto relative overflow-hidden w-full max-w-lg rounded-2xl bg-[#fff]
                    border shadow-[0_18px_40px_-12px_rgb(16_24_40_/_0.28)]
                    ${isError ? 'border-red-200' : 'border-emerald-200'}
                    ${leaving ? 'animate-toast-out' : 'animate-toast-in'}`}
            >
                <div className={`absolute inset-y-0 left-0 w-1.5 ${isError ? 'bg-red-500' : 'bg-emerald-500'}`} />

                <div className="relative flex items-start gap-3 p-3.5 sm:p-4 pl-5 pr-10">
                    <div className={`shrink-0 w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center
                        ${isError ? 'bg-red-50 ring-1 ring-red-200' : 'bg-emerald-50 ring-1 ring-emerald-200 animate-pulse-ring'}`}>
                        {isError ? (
                            <XCircleIcon className="w-6 h-6 text-[#dc2626]" />
                        ) : (
                            <svg viewBox="0 0 52 52" className="w-6 h-6 sm:w-7 sm:h-7">
                                <circle cx="26" cy="26" r="23" fill="none" stroke="#10b981" strokeWidth="3"
                                        className="animate-draw-circle" />
                                <path d="M15 27l8 8 15-16" fill="none" stroke="#059669" strokeWidth="4"
                                      strokeLinecap="round" strokeLinejoin="round" className="animate-draw-check" />
                            </svg>
                        )}
                    </div>

                    <div className="min-w-0 pt-0.5">
                        <p className={`font-semibold text-sm ${isError ? 'text-[#b91c1c]' : 'text-[#047857]'}`}>
                            {isError ? 'Terjadi Kesalahan' : 'Berhasil!'}
                        </p>
                        <p className="text-slate-300 text-[13px] sm:text-sm mt-0.5 leading-relaxed break-words">{msg}</p>
                    </div>

                    <button
                        onClick={() => setLeaving(true)}
                        className="absolute top-2 right-2 w-9 h-9 flex items-center justify-center text-slate-500 hover:text-slate-200 transition-colors"
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

/** Tutup popover saat klik di luar elemen. */
function useOutside(ref, onOut) {
    useEffect(() => {
        const h = (e) => { if (ref.current && !ref.current.contains(e.target)) onOut(); };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, [ref, onOut]);
}

/** Pencarian menu: cari halaman yang boleh diakses user lalu buka. */
function MenuSearch({ items }) {
    const [q, setQ] = useState('');
    const [open, setOpen] = useState(false);
    const [cursor, setCursor] = useState(0);
    const inputRef = useRef(null);
    const boxRef = useRef(null);
    useOutside(boxRef, () => setOpen(false));

    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === '/') { e.preventDefault(); inputRef.current?.focus(); setOpen(true); }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const results = useMemo(() => {
        const s = q.trim().toLowerCase();
        if (!s) return items.slice(0, 8);
        return items.filter((it) => `${it.label} ${it.group || ''}`.toLowerCase().includes(s)).slice(0, 8);
    }, [q, items]);

    const go = (it) => { setOpen(false); setQ(''); inputRef.current?.blur(); router.visit(it.href); };

    return (
        <div ref={boxRef} className="relative flex-1 max-w-xl min-w-0">
            <MagnifyingGlassIcon className="w-[18px] h-[18px] text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
                ref={inputRef}
                value={q}
                onChange={(e) => { setQ(e.target.value); setCursor(0); setOpen(true); }}
                onFocus={() => setOpen(true)}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') { e.preventDefault(); setCursor((c) => Math.min(c + 1, results.length - 1)); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); setCursor((c) => Math.max(c - 1, 0)); }
                    else if (e.key === 'Enter' && results[cursor]) { e.preventDefault(); go(results[cursor]); }
                    else if (e.key === 'Escape') { setOpen(false); e.currentTarget.blur(); }
                }}
                placeholder="Cari menu, data, atau laporan... (Ctrl + /)"
                className="w-full h-11 pl-10 pr-4 rounded-xl bg-slate-900 border border-[#e3e9f0] text-sm text-slate-100
                           placeholder:text-slate-500 focus:outline-none focus:bg-[#fff] focus:border-brand-400 focus:ring-4 focus:ring-brand-100
                           transition-all duration-150"
                aria-label="Cari menu"
            />
            {open && (
                <div className="absolute z-40 mt-2 w-full rounded-xl bg-[#fff] border border-[#e3e9f0] shadow-[0_18px_40px_-12px_rgb(16_24_40_/_0.25)] py-1.5 animate-fade-in">
                    {results.length === 0 ? (
                        <p className="px-4 py-3 text-sm text-slate-500">Menu “{q}” tidak ditemukan.</p>
                    ) : results.map((it, i) => (
                        <button
                            key={it.href}
                            onMouseEnter={() => setCursor(i)}
                            onClick={() => go(it)}
                            className={`w-full flex items-center gap-3 px-3.5 py-2 text-left text-sm transition-colors
                                ${i === cursor ? 'bg-brand-50 text-brand-700' : 'text-slate-200 hover:bg-slate-900'}`}
                        >
                            <it.icon className={`w-5 h-5 shrink-0 ${themeOf(it).text}`} />
                            <span className="flex-1 truncate">{it.label}</span>
                            {it.group && <span className="text-[11px] text-slate-500">{it.group}</span>}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function AppLayout({ children, title }) {
    const { auth, pendingUsersCount } = usePage().props;
    const user = auth?.user;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(() => {
        try { return localStorage.getItem('gep.sidebar') !== 'closed'; } catch { return true; }
    });
    const [profileOpen, setProfileOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const profileRef = useRef(null);
    const notifRef = useRef(null);
    useOutside(profileRef, () => setProfileOpen(false));
    useOutside(notifRef, () => setNotifOpen(false));

    // Tutup drawer setiap pindah halaman
    useEffect(() => router.on('navigate', () => { setDrawerOpen(false); setProfileOpen(false); setNotifOpen(false); }), []);

    // Kunci scroll body saat drawer terbuka (mobile)
    useEffect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    }, [drawerOpen]);

    // Tutup dengan tombol Esc
    useEffect(() => {
        const onKey = (e) => { if (e.key === 'Escape') { setDrawerOpen(false); setProfileOpen(false); setNotifOpen(false); } };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const toggleSidebar = () => {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            setSidebarOpen((v) => {
                try { localStorage.setItem('gep.sidebar', v ? 'closed' : 'open'); } catch { /* abaikan */ }
                return !v;
            });
        } else {
            setDrawerOpen(true);
        }
    };

    const canSee = (item) => canSeeItem(item, permissions, roles);

    const isActive = (href) => {
        const path = window.location.pathname;
        return href === '/' ? path === '/' : path.startsWith(href);
    };

    const initials = (user?.name ?? 'U')
        .split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();

    const visible = nav.filter(canSee);
    const topItems = visible.filter((it) => !it.group && !LOOSE_META[it.href]);

    // Susun seksi: tiap seksi berisi grup (collapsible) & item lepas, urut sesuai `nav`.
    const sectionBlocks = SECTIONS.map((name) => {
        const entries = [];
        visible.forEach((it) => {
            if (it.group) {
                if (GROUP_META[it.group]?.section !== name) return;
                let g = entries.find((e) => e.type === 'group' && e.name === it.group);
                if (!g) { g = { type: 'group', name: it.group, items: [] }; entries.push(g); }
                g.items.push(it);
            } else if (LOOSE_META[it.href]?.section === name) {
                entries.push({ type: 'link', item: it });
            }
        });
        return { name, entries };
    }).filter((s) => s.entries.length > 0);

    // Buka grup yang memuat halaman aktif; sisanya tertutup agar tidak penuh scroll.
    const [openGroups, setOpenGroups] = useState(() => {
        const path = window.location.pathname;
        const st = {};
        nav.forEach((it) => {
            if (it.group) {
                const act = it.href === '/' ? path === '/' : path.startsWith(it.href);
                if (act) st[it.group] = true;
            }
        });
        return st;
    });
    const toggleGroup = (g) => setOpenGroups((s) => ({ ...s, [g]: !s[g] }));

    const pendingBadge = (item) => item.href === '/admin/users' && pendingUsersCount > 0 && (
        <span className="min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-[#fff] text-[10px] font-bold flex items-center justify-center animate-bounce-soft">
            {pendingUsersCount}
        </span>
    );

    // Item level 1. Dashboard & Menu Aplikasi: pil biru saat aktif. Item modul (Tugas/User): warna modulnya.
    const renderMain = (item, i) => {
        const active = isActive(item.href);
        const meta = LOOSE_META[item.href];
        const t = meta ? THEMES[meta.theme] : null;
        const Icon = meta?.icon || item.icon;
        const activeCls = t
            ? `${t.solid} text-[#fff] ${t.glow}`
            : 'bg-gradient-to-r from-brand-500 to-brand-600 text-white shadow-[0_8px_18px_-8px_rgb(15_140_189_/_0.7)]';
        return (
            <Link
                key={item.href}
                href={item.href}
                style={{ animationDelay: `${i * 40}ms` }}
                className={`group relative flex items-center gap-3 px-3.5 min-h-[44px] rounded-xl text-sm font-medium
                    animate-slide-in-left transition-all duration-200
                    ${active ? activeCls : 'text-slate-300 hover:text-slate-100 hover:bg-[#f1f6fb]'}`}
            >
                <span className={`w-8 h-8 -ml-1 rounded-lg flex items-center justify-center shrink-0 transition-transform duration-300
                    ${active ? 'bg-[#fff]/20 text-[#fff]' : `${t ? `${t.soft} ${t.text}` : 'bg-[#eef4fb] text-[#1769c2]'} lg:group-hover:scale-110`}`}>
                    <Icon className="w-[19px] h-[19px]" />
                </span>
                <span className="flex-1 truncate">{item.label}</span>
                {pendingBadge(item)}
            </Link>
        );
    };

    // Sub-item di dalam grup (level 2) — ikon berwarna sesuai modul.
    const renderSub = (item, i) => {
        const active = isActive(item.href);
        const t = themeOf(item);
        return (
            <Link
                key={item.href}
                href={item.href}
                style={{ animationDelay: `${i * 35}ms` }}
                className={`group relative flex items-center gap-2.5 pl-11 pr-3 min-h-[38px] rounded-lg text-[13px]
                    animate-slide-in-left transition-colors duration-150
                    ${active ? `${t.soft} ${t.text} font-semibold` : `text-slate-300 hover:text-slate-100 ${t.hover}`}`}
            >
                {active && <span className={`absolute left-6 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full ${t.solid}`} />}
                <item.icon className={`w-[18px] h-[18px] shrink-0 ${t.text}`} />
                <span className="flex-1 truncate">{item.label}</span>
                {pendingBadge(item)}
            </Link>
        );
    };

    const renderGroup = (g, gi) => {
        const open = !!openGroups[g.name];
        const hasActive = g.items.some((it) => isActive(it.href));
        const meta = GROUP_META[g.name] || {};
        const t = THEMES[meta.theme] || THEMES.blue;
        const Icon = meta.icon;
        return (
            <div key={g.name}>
                <button
                    onClick={() => toggleGroup(g.name)}
                    style={{ animationDelay: `${gi * 40}ms` }}
                    aria-expanded={open}
                    className={`group w-full flex items-center gap-3 px-3.5 min-h-[44px] rounded-xl text-sm font-medium
                        animate-slide-in-left transition-colors duration-200
                        ${hasActive ? `${t.text} ${t.soft}` : `text-slate-300 hover:text-slate-100 ${t.hover}`}`}
                >
                    <span className={`w-8 h-8 -ml-1 rounded-lg flex items-center justify-center shrink-0 transition-transform duration-300 lg:group-hover:scale-110
                        ${hasActive ? `${t.solid} text-[#fff] ${t.glow}` : `${t.soft} ${t.text}`}`}>
                        {Icon && <Icon className="w-[18px] h-[18px]" />}
                    </span>
                    <span className="flex-1 text-left truncate">{g.name}</span>
                    {!open && hasActive && <span className={`w-1.5 h-1.5 rounded-full ${t.solid}`} />}
                    <ChevronRightIcon className={`w-4 h-4 text-slate-500 transition-transform duration-200 ${open ? 'rotate-90' : ''}`} />
                </button>
                {open && <div className="mt-0.5 mb-1 space-y-0.5">{g.items.map((it, i) => renderSub(it, i))}</div>}
            </div>
        );
    };

    const SidebarContent = (
        <>
            <div className="px-5 h-[76px] flex items-center gap-3 shrink-0">
                <div className="min-w-0 flex-1">
                    <img
                        src="/images/logo-gep.png"
                        alt="PT Geosys Energi Prima"
                        className="h-[52px] w-auto object-contain transition-transform duration-300 hover:scale-105 origin-left"
                    />
                </div>
                <button
                    onClick={() => setDrawerOpen(false)}
                    className="lg:hidden w-9 h-9 -mr-1 flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-100 hover:bg-[#f1f6fb] transition-colors"
                    aria-label="Tutup menu"
                >
                    <XMarkIcon className="w-5 h-5" />
                </button>
            </div>

            <nav className="flex-1 overflow-y-auto px-3 pt-2 pb-4">
                <div className="space-y-1">{topItems.map((it, i) => renderMain(it, i))}</div>

                {sectionBlocks.map((sec) => (
                    <div key={sec.name} className="mt-5">
                        <p className="px-3.5 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{sec.name}</p>
                        <div className="space-y-1">
                            {sec.entries.map((e, i) => (e.type === 'group' ? renderGroup(e, i) : renderMain(e.item, i)))}
                        </div>
                    </div>
                ))}
            </nav>

            {/* Kartu dekoratif bawah */}
            <div className="px-3 pb-[calc(env(safe-area-inset-bottom)+0.9rem)] shrink-0">
                <div className="relative overflow-hidden rounded-2xl p-4 h-[112px] bg-gradient-to-br from-[#0f5132] via-[#1f7a4a] to-[#2e9d5f] text-white">
                    <svg viewBox="0 0 200 120" className="absolute inset-0 w-full h-full opacity-30" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                        <path d="M0 95 C30 70 45 80 60 60 C75 40 95 55 110 35 C125 15 150 40 165 25 C180 10 195 20 200 15 L200 120 L0 120Z" fill="#0b3d24" />
                        <path d="M0 110 C40 90 70 105 100 85 C130 65 160 90 200 70 L200 120 L0 120Z" fill="#08301c" />
                        {[20, 48, 76, 140, 170].map((x, i) => (
                            <g key={x} transform={`translate(${x} ${58 - (i % 2) * 10})`}>
                                <rect x="-1.5" y="18" width="3" height="16" fill="#06240f" />
                                <path d="M0 0 L-11 22 L11 22Z" fill="#0d4a2a" />
                            </g>
                        ))}
                    </svg>
                    <p className="relative font-bold text-[17px] leading-tight drop-shadow">Energi Bersih<br />Untuk Masa Depan</p>
                    <p className="relative mt-2 text-[10px] text-[#fff]/75">Database Sumber Biomassa by Ralie©2026</p>
                </div>
            </div>
        </>
    );

    const roleLabel = roles[0] ? roles[0].replace('_', ' ') : null;

    return (
        <div className="flex app-viewport bg-slate-900 overflow-hidden">
            {/* Sidebar tetap — laptop & monitor besar */}
            <aside className={`hidden ${sidebarOpen ? 'lg:flex' : ''} w-64 xl:w-[270px] shrink-0 bg-[#fff] border-r border-[#e9eef4] flex-col
                              pl-[env(safe-area-inset-left)]`}>
                {SidebarContent}
            </aside>

            {/* Drawer — HP & tablet */}
            <div className={`lg:hidden fixed inset-0 z-50 ${drawerOpen ? '' : 'pointer-events-none'}`}>
                <div
                    onClick={() => setDrawerOpen(false)}
                    className={`absolute inset-0 bg-[#0f172a]/40 backdrop-blur-sm transition-opacity duration-300
                                ${drawerOpen ? 'opacity-100' : 'opacity-0'}`}
                />
                <aside className={`absolute inset-y-0 left-0 w-[82vw] max-w-[19rem] bg-[#fff] border-r border-[#e9eef4]
                                   flex flex-col transition-transform duration-300 ease-out
                                   pl-[env(safe-area-inset-left)]
                                   ${drawerOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full shadow-none'}`}>
                    {SidebarContent}
                </aside>
            </div>

            {/* Konten utama */}
            <div className="flex-1 flex flex-col overflow-hidden min-w-0">
                <header className="h-16 lg:h-[76px] shrink-0 bg-slate-900/90 backdrop-blur
                                   px-3 sm:px-5 lg:px-6 flex items-center gap-3 sm:gap-4 sticky top-0 z-30
                                   pt-[env(safe-area-inset-top)] pr-[calc(env(safe-area-inset-right)+0.75rem)]">
                    <button
                        onClick={toggleSidebar}
                        className="w-10 h-10 -ml-1 shrink-0 flex items-center justify-center rounded-lg
                                   text-slate-300 hover:text-slate-100 hover:bg-[#fff] transition-colors"
                        aria-label="Buka/tutup menu"
                    >
                        <Bars3Icon className="w-6 h-6" />
                    </button>

                    <div className="hidden md:flex flex-1 min-w-0">
                        <MenuSearch items={visible} />
                    </div>
                    <h1 className="md:hidden text-slate-100 font-semibold text-[15px] tracking-tight truncate flex-1 min-w-0">
                        {title}
                    </h1>

                    <div className="flex items-center gap-1 sm:gap-2 shrink-0">
                        {/* Notifikasi: akun menunggu aktivasi (khusus Super Admin) */}
                        <div ref={notifRef} className="relative">
                            <button
                                onClick={() => setNotifOpen((v) => !v)}
                                className="relative w-10 h-10 flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-100 hover:bg-[#fff] transition-colors"
                                aria-label="Notifikasi"
                            >
                                <BellIcon className="w-6 h-6" />
                                {pendingUsersCount > 0 && (
                                    <span className="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-[#ef4444] text-[#fff] text-[10px] font-bold flex items-center justify-center ring-2 ring-[#f4f7fb]">
                                        {pendingUsersCount}
                                    </span>
                                )}
                            </button>
                            {notifOpen && (
                                <div className="absolute right-0 mt-2 w-72 rounded-xl bg-[#fff] border border-[#e3e9f0] shadow-[0_18px_40px_-12px_rgb(16_24_40_/_0.25)] p-2 z-40 animate-fade-in">
                                    <p className="px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Notifikasi</p>
                                    {pendingUsersCount > 0 ? (
                                        <Link href="/admin/users" className="flex items-start gap-3 rounded-lg px-2 py-2.5 hover:bg-slate-900">
                                            <UsersColorIcon className="w-8 h-8 shrink-0" />
                                            <span className="text-sm text-slate-200">
                                                <b>{pendingUsersCount} akun</b> menunggu aktivasi.
                                                <span className="block text-xs text-brand-600 mt-0.5">Buka Manajemen User →</span>
                                            </span>
                                        </Link>
                                    ) : (
                                        <p className="px-2 py-3 text-sm text-slate-500">Tidak ada notifikasi baru.</p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Profil */}
                        <div ref={profileRef} className="relative">
                            <button
                                onClick={() => setProfileOpen((v) => !v)}
                                className="flex items-center gap-2.5 pl-1 pr-2 py-1 rounded-xl hover:bg-[#fff] transition-colors"
                                aria-label="Menu profil"
                            >
                                <span className="w-10 h-10 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-white flex items-center justify-center text-sm font-bold ring-2 ring-[#fff] shadow">
                                    {initials}
                                </span>
                                <span className="hidden sm:block text-left min-w-0 max-w-[180px]">
                                    <span className="block text-sm font-semibold text-slate-100 truncate">{user?.name}</span>
                                    <span className="block text-[11px] text-slate-500 truncate">PT Geosys Energi Prima</span>
                                </span>
                                <ChevronDownIcon className={`w-4 h-4 text-slate-500 hidden sm:block transition-transform ${profileOpen ? 'rotate-180' : ''}`} />
                            </button>
                            {profileOpen && (
                                <div className="absolute right-0 mt-2 w-64 rounded-xl bg-[#fff] border border-[#e3e9f0] shadow-[0_18px_40px_-12px_rgb(16_24_40_/_0.25)] p-2 z-40 animate-fade-in">
                                    <div className="px-2 py-2">
                                        <p className="text-sm font-semibold text-slate-100 truncate">{user?.name}</p>
                                        <p className="text-xs text-slate-500 truncate">{user?.email}</p>
                                        <div className="flex flex-wrap items-center gap-1.5 mt-2">
                                            {roleLabel && <span className="badge badge-blue capitalize">{roleLabel}</span>}
                                            {user?.division?.name && <span className="badge badge-slate">{user.division.name}</span>}
                                        </div>
                                    </div>
                                    <div className="h-px bg-[#eef2f6] my-1" />
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="w-full flex items-center gap-2 px-2 min-h-[40px] rounded-lg text-sm text-slate-300 hover:text-[#dc2626] hover:bg-[#fef2f2] transition-colors"
                                    >
                                        <ArrowRightOnRectangleIcon className="w-4 h-4" /> Keluar
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                <main className="flex-1 overflow-y-auto overscroll-contain">
                    <div className="max-w-[1600px] mx-auto p-3 sm:p-5 lg:p-6 animate-fade-in
                                    pb-[calc(env(safe-area-inset-bottom)+1rem)]
                                    pl-[calc(env(safe-area-inset-left)+0.75rem)] pr-[calc(env(safe-area-inset-right)+0.75rem)]
                                    sm:pl-5 sm:pr-5 lg:pl-6 lg:pr-6">
                        {title && !['/', '/tasks', '/menu'].includes(window.location.pathname) && (
                            <h1 className="hidden md:block print:hidden text-slate-100 font-bold text-xl tracking-tight mb-4 animate-fade-in">{title}</h1>
                        )}
                        {children}
                    </div>
                </main>
            </div>

            <Flash />
        </div>
    );
}
