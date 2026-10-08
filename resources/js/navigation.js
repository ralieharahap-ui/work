// Sumber tunggal menu aplikasi — dipakai sidebar (AppLayout) & halaman Menu Aplikasi.
import {
    HomeIcon, Squares2X2Icon, GlobeAsiaAustraliaIcon, BuildingOffice2Icon, MapIcon, CalculatorIcon,
    ListBulletIcon, BookOpenIcon, BookmarkSquareIcon, CubeIcon, TruckIcon, UserGroupIcon, BanknotesIcon,
    ReceiptPercentIcon, TableCellsIcon, ScaleIcon, ArrowTrendingUpIcon, ClipboardDocumentListIcon, ChartBarIcon,
    DocumentPlusIcon, ArchiveBoxIcon, ViewColumnsIcon, UsersIcon,
} from '@heroicons/react/24/outline';
import {
    TruckIcon as TruckSolid, CalculatorIcon as CalculatorSolid, ChartBarIcon as ChartBarSolid,
    FolderIcon as FolderSolid, ViewColumnsIcon as ViewColumnsSolid, UsersIcon as UsersSolid,
} from '@heroicons/react/24/solid';

export const nav = [
    { label: 'Dashboard',        href: '/',                   icon: HomeIcon,       perm: null },
    { label: 'Menu Aplikasi',    href: '/menu',               icon: Squares2X2Icon, perm: null },
    { label: 'Sumber Cangkang',  href: '/palm-oil-sources',   icon: GlobeAsiaAustraliaIcon, perm: 'inventory.view',   group: 'Supply Chain' },
    { label: 'Titik Bongkar',    href: '/unloading-points',   icon: BuildingOffice2Icon,    perm: 'inventory.view',   group: 'Supply Chain' },
    { label: 'Titik Dermaga',    href: '/jetty-points',       icon: MapIcon,                perm: 'inventory.view',   group: 'Supply Chain' },
    { label: 'Kalkulasi Proyek', href: '/project-calculator', icon: CalculatorIcon,         perm: 'inventory.view',   group: 'Supply Chain' },
    // ── Akuntansi / Pembukuan ──
    { label: 'Daftar Akun',      href: '/books/accounts',      icon: ListBulletIcon,     perm: 'books.accounts.view', group: 'Akuntansi' },
    { label: 'Jurnal Umum',      href: '/books/journal',       icon: BookOpenIcon,       perm: 'books.journal.view', group: 'Akuntansi' },
    { label: 'Buku Besar',       href: '/books/ledger',        icon: BookmarkSquareIcon, perm: 'books.ledger.view', group: 'Akuntansi' },
    { label: 'Daftar Aset',      href: '/books/fixed-assets',  icon: CubeIcon,           perm: 'books.assets.view', group: 'Akuntansi' },
    { label: 'Master Vendor',    href: '/books/vendors',       icon: TruckIcon,          perm: 'books.vendors.view', group: 'Akuntansi' },
    { label: 'Master Customer',  href: '/books/customers',     icon: UserGroupIcon,      perm: 'books.customers.view', group: 'Akuntansi' },
    { label: 'Kreditur Pendanaan', href: '/books/creditors',   icon: BanknotesIcon,      perm: 'books.creditors.view', group: 'Akuntansi' },
    { label: 'Kontrol PPN',      href: '/books/tax-control',   icon: ReceiptPercentIcon, perm: 'books.tax.view', group: 'Akuntansi' },
    { label: 'Neraca Lajur',     href: '/books/worksheet',     icon: TableCellsIcon,     perm: 'books.reports.view', group: 'Laporan' },
    { label: 'Neraca',           href: '/books/balance-sheet', icon: ScaleIcon,          perm: 'books.reports.view', group: 'Laporan' },
    { label: 'Laba/Rugi',        href: '/books/profit-loss',   icon: ArrowTrendingUpIcon, perm: 'books.reports.view', group: 'Laporan' },
    { label: 'Neraca Saldo',     href: '/books/trial-balance', icon: ClipboardDocumentListIcon, perm: 'books.reports.view', group: 'Laporan' },
    { label: 'Peredaran Bruto',  href: '/books/gross-turnover',icon: ChartBarIcon,       perm: 'books.reports.view', group: 'Laporan' },
    // ── Dokumen ──
    { label: 'Dokumen Template', href: '/documents',          icon: DocumentPlusIcon, perm: 'letters.view', group: 'Dokumen' },
    { label: 'Dokumentasi',      href: '/documents/log',      icon: ArchiveBoxIcon,   perm: 'letters.view', group: 'Dokumen' },
    // ── Manajemen ──
    { label: 'Manajemen Tugas',  href: '/tasks',              icon: ViewColumnsIcon, perm: 'tasks.view' },
    { label: 'Manajemen User',   href: '/admin/users',        icon: UsersIcon,       role: 'super_admin' },
];

// Palet per modul. Kelas ditulis utuh agar terbaca Tailwind.
export const THEMES = {
    green: {
        solid: 'bg-[#22a35a]', soft: 'bg-[#eaf8f0]', text: 'text-[#178a48]', hover: 'hover:bg-[#eaf8f0]',
        head: 'from-[#e6f7ed] to-[#f7fdf9]', border: 'border-[#d9f0e3]', glow: 'shadow-[0_8px_18px_-8px_rgb(34_163_90_/_0.65)]',
    },
    orange: {
        solid: 'bg-[#f08a1c]', soft: 'bg-[#fff3e3]', text: 'text-[#cf6c05]', hover: 'hover:bg-[#fff3e3]',
        head: 'from-[#fff0dc] to-[#fffaf3]', border: 'border-[#fbe5c9]', glow: 'shadow-[0_8px_18px_-8px_rgb(240_138_28_/_0.65)]',
    },
    blue: {
        solid: 'bg-[#1e88e5]', soft: 'bg-[#e9f3fe]', text: 'text-[#1769c2]', hover: 'hover:bg-[#e9f3fe]',
        head: 'from-[#e5f0fe] to-[#f6faff]', border: 'border-[#d6e7fb]', glow: 'shadow-[0_8px_18px_-8px_rgb(30_136_229_/_0.65)]',
    },
    sky: {
        solid: 'bg-[#13a6c9]', soft: 'bg-[#e6f7fb]', text: 'text-[#0a84a3]', hover: 'hover:bg-[#e6f7fb]',
        head: 'from-[#e1f5fa] to-[#f5fcfe]', border: 'border-[#cfedf5]', glow: 'shadow-[0_8px_18px_-8px_rgb(19_166_201_/_0.65)]',
    },
    violet: {
        solid: 'bg-[#7c5cf0]', soft: 'bg-[#f1edfe]', text: 'text-[#5f40d6]', hover: 'hover:bg-[#f1edfe]',
        head: 'from-[#ede8fe] to-[#faf8ff]', border: 'border-[#e2dbfc]', glow: 'shadow-[0_8px_18px_-8px_rgb(124_92_240_/_0.65)]',
    },
    rose: {
        solid: 'bg-[#ef4f6a]', soft: 'bg-[#fdecef]', text: 'text-[#d3314e]', hover: 'hover:bg-[#fdecef]',
        head: 'from-[#fde8ec] to-[#fff7f8]', border: 'border-[#f9d6dd]', glow: 'shadow-[0_8px_18px_-8px_rgb(239_79_106_/_0.65)]',
    },
};

// Modul berkelompok (collapsible di sidebar).
export const GROUP_META = {
    'Supply Chain': { section: 'Utama',     theme: 'green',  icon: TruckSolid,      desc: 'Sumber cangkang, titik bongkar & dermaga' },
    'Akuntansi':    { section: 'Keuangan',  theme: 'orange', icon: CalculatorSolid, desc: 'Pencatatan keuangan perusahaan' },
    'Laporan':      { section: 'Keuangan',  theme: 'blue',   icon: ChartBarSolid,   desc: 'Laporan keuangan & peredaran bruto' },
    'Dokumen':      { section: 'Manajemen', theme: 'sky',    icon: FolderSolid,     desc: 'Template & arsip dokumen terpusat' },
};

// Item lepas yang masuk seksi tertentu.
export const LOOSE_META = {
    '/tasks':       { section: 'Manajemen', theme: 'violet', icon: ViewColumnsSolid, desc: 'Kanban, proyek & tim kerja' },
    '/admin/users': { section: 'Manajemen', theme: 'rose',   icon: UsersSolid,       desc: 'Akun, role & hak akses pengguna' },
};

export const SECTIONS = ['Utama', 'Keuangan', 'Manajemen'];

export const canSee = (item, permissions = [], roles = []) => {
    if (item.role) return roles.includes(item.role);
    if (!item.perm) return true;
    return permissions.includes(item.perm);
};

export const themeOf = (item) => THEMES[(item.group ? GROUP_META[item.group]?.theme : LOOSE_META[item.href]?.theme) || 'blue'];
