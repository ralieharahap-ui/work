import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRightIcon, ArrowUpIcon, ArrowDownIcon, ChevronDownIcon } from '@heroicons/react/24/outline';
import {
    PalmPlantationIcon, IndustryIcon, JettyIcon, DocumentColorIcon, JournalColorIcon, ReportColorIcon,
    VendorColorIcon, TaskBoardColorIcon, ArchiveColorIcon,
} from '@/Components/AppIcons';

const nf = (n, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(n ?? 0);
const rupiahShort = (n) => {
    const v = Number(n) || 0;
    if (Math.abs(v) >= 1e12) return `Rp ${nf(v / 1e12, 2)} T`;
    if (Math.abs(v) >= 1e9) return `Rp ${nf(v / 1e9, 2)} M`;
    if (Math.abs(v) >= 1e6) return `Rp ${nf(v / 1e6, 2)} Jt`;
    return `Rp ${nf(v)}`;
};
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
const DOC_LABEL = {
    invoice: 'Invoice', faktur: 'Faktur', kwitansi: 'Kwitansi', surat_jalan: 'Surat Jalan', voucher_jurnal: 'Voucher Jurnal',
    tanda_terima: 'Tanda Terima', perjalanan_dinas: 'Perjalanan Dinas', reimbursement: 'Reimbursement', po: 'Purchase Order',
    do: 'Delivery Order', surat_resmi: 'Surat Resmi', journal: 'Jurnal Umum',
};
const STATUS_LABEL = {
    on_review: 'On Review', signed: 'Ditandatangani', released: 'Dirilis', cancelled: 'Dibatalkan',
    draft: 'Draft', pending: 'Menunggu Approval', posted: 'Posted', rejected: 'Ditolak',
};

const waktu = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    const yest = new Date(now); yest.setDate(now.getDate() - 1);
    if (sameDay) return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    if (d.toDateString() === yest.toDateString()) return 'Kemarin';
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};

function Trend({ value }) {
    if (value === null || value === undefined) return null;
    const up = value >= 0;
    return (
        <span className={`inline-flex items-center gap-0.5 text-[11px] font-semibold px-1.5 py-0.5 rounded-md
            ${up ? 'bg-[#e8f8ee] text-[#15803d]' : 'bg-[#fdecec] text-[#dc2626]'}`}>
            {up ? <ArrowUpIcon className="w-3 h-3" /> : <ArrowDownIcon className="w-3 h-3" />}
            {nf(Math.abs(value), 1)}%
        </span>
    );
}

function Kpi({ icon, tint, label, value, unit, trend, href, note, delay }) {
    return (
        <div className="card !p-5 flex flex-col animate-slide-up" style={{ animationDelay: `${delay}ms` }}>
            <div className="flex items-start gap-4">
                <span className={`w-14 h-14 rounded-2xl flex items-center justify-center shrink-0 ${tint}`}>{icon}</span>
                <div className="min-w-0 flex-1">
                    <p className="text-slate-300 text-sm font-medium">{label}</p>
                    <p className="text-slate-100 text-2xl font-bold mt-1 tabular leading-tight truncate">
                        {value}{unit && <span className="text-base font-semibold ml-1">{unit}</span>}
                    </p>
                </div>
            </div>
            <div className="flex items-center justify-between mt-3 min-h-[22px]">
                {trend !== undefined && trend !== null ? <Trend value={trend} /> : <span className="text-[11px] text-slate-500">{note}</span>}
                {href && (
                    <Link href={href} className="inline-flex items-center gap-1 text-[13px] font-medium text-brand-600 hover:text-brand-700 group">
                        Lihat Detail <ArrowRightIcon className="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
                    </Link>
                )}
            </div>
        </div>
    );
}

/** Ikon tahap alur proses (SVG sederhana, tanpa aset luar). */
const StepArt = {
    kebun: <PalmPlantationIcon className="w-12 h-12" />,
    pks: <IndustryIcon className="w-12 h-12" />,
    stock: (
        <svg viewBox="0 0 48 48" className="w-12 h-12" aria-hidden="true">
            <ellipse cx="24" cy="40" rx="20" ry="4" fill="#d6c3a5" />
            <path d="M5 40 C10 22 18 14 24 12 C30 14 38 22 43 40Z" fill="#7a5532" />
            <path d="M14 40 C17 28 21 22 24 20 C27 22 31 28 34 40Z" fill="#5b3d22" />
            <circle cx="18" cy="31" r="1.4" fill="#a87b4f" /><circle cx="28" cy="27" r="1.4" fill="#a87b4f" /><circle cx="31" cy="34" r="1.4" fill="#a87b4f" />
        </svg>
    ),
    truck: (
        <svg viewBox="0 0 48 48" className="w-12 h-12" aria-hidden="true">
            <rect x="3" y="14" width="27" height="18" rx="2" fill="#cfd8e3" stroke="#94a3b8" />
            <path d="M30 20h8l6 7v5H30z" fill="#22a15a" />
            <rect x="33" y="22" width="6" height="5" rx="1" fill="#d1fae5" />
            <circle cx="11" cy="34" r="4" fill="#334155" /><circle cx="37" cy="34" r="4" fill="#334155" />
            <circle cx="11" cy="34" r="1.6" fill="#cbd5e1" /><circle cx="37" cy="34" r="1.6" fill="#cbd5e1" />
        </svg>
    ),
    jetty: <JettyIcon className="w-12 h-12" />,
    pltu: (
        <svg viewBox="0 0 48 48" className="w-12 h-12" aria-hidden="true">
            <rect x="6" y="10" width="4" height="24" fill="#ef4444" /><rect x="6" y="14" width="4" height="3" fill="#fff" />
            <rect x="13" y="6" width="4" height="28" fill="#ef4444" /><rect x="13" y="10" width="4" height="3" fill="#fff" />
            <path d="M4 26h40v16H4z" fill="#94a3b8" />
            <rect x="24" y="16" width="18" height="12" fill="#cbd5e1" />
            {[0, 1, 2].map((i) => <rect key={i} x={27 + i * 5} y="19" width="3" height="3" fill="#0ea5e9" />)}
            {[0, 1, 2, 3, 4].map((i) => <rect key={i} x={7 + i * 7} y="32" width="4" height="4" fill="#e2e8f0" />)}
        </svg>
    ),
};

function ProcessFlow({ summary }) {
    const steps = [
        { key: 'kebun', label: 'Kebun Sawit', href: null },
        { key: 'pks',   label: 'PKS / Sumber', sub: `${nf(summary.source_count)} sumber`, href: '/palm-oil-sources' },
        { key: 'stock', label: 'Stockpile', sub: `${nf(summary.total_volume)} ton`, href: '/palm-oil-sources' },
        { key: 'truck', label: 'Transportasi', sub: 'Kalkulasi biaya', href: '/project-calculator' },
        { key: 'jetty', label: 'Dermaga', sub: `${nf(summary.jetty_count)} titik`, href: '/jetty-points' },
        { key: 'pltu',  label: 'Pabrik / PLTU', sub: `${nf(summary.customer_count)} titik bongkar`, href: '/unloading-points' },
    ];
    return (
        <div className="card !p-5 h-full flex flex-col animate-slide-up" style={{ animationDelay: '260ms' }}>
            <h3 className="text-slate-100 font-bold text-lg mb-4">Alur Proses Biomassa</h3>
            <div className="flex-1 flex items-center justify-between gap-1 overflow-x-auto pb-1">
                {steps.map((s, i) => {
                    const body = (
                        <div className="flex flex-col items-center text-center min-w-[78px] group">
                            <span className="w-16 h-16 rounded-2xl bg-[#f6f9fc] flex items-center justify-center transition-transform duration-300 group-hover:-translate-y-1 group-hover:scale-105">
                                {StepArt[s.key]}
                            </span>
                            <span className="text-[13px] font-medium text-slate-200 mt-2 leading-tight">{s.label}</span>
                            {s.sub && <span className="text-[11px] text-slate-500 mt-0.5">{s.sub}</span>}
                        </div>
                    );
                    return (
                        <div key={s.key} className="flex items-start">
                            {s.href ? <Link href={s.href}>{body}</Link> : body}
                            {i < steps.length - 1 && <ArrowRightIcon className="w-5 h-5 text-brand-400 mt-6 mx-1 shrink-0" />}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function ProjectProgress({ projects }) {
    const colors = ['from-[#22c55e] to-[#16a34a]', 'from-brand-400 to-brand-600', 'from-[#f59e0b] to-[#d97706]'];
    return (
        <div className="card !p-5 h-full animate-slide-up" style={{ animationDelay: '320ms' }}>
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-slate-100 font-bold text-lg">Progress Proyek</h3>
                <Link href="/tasks" className="text-[13px] font-medium text-brand-600 hover:text-brand-700">Semua Proyek</Link>
            </div>
            {projects.length === 0 ? (
                <p className="text-sm text-slate-500 py-6 text-center">Belum ada proyek berjalan.</p>
            ) : (
                <div className="space-y-5">
                    {projects.map((p, i) => {
                        const pct = p.tasks > 0 ? Math.round((p.done / p.tasks) * 100) : 0;
                        return (
                            <div key={p.title}>
                                <div className="flex items-baseline justify-between gap-3">
                                    <p className="font-semibold text-slate-100 text-sm truncate">{p.title}</p>
                                    <p className="font-semibold text-slate-100 text-sm tabular shrink-0">{pct}%</p>
                                </div>
                                <div className="h-2.5 rounded-full bg-[#eef2f6] mt-2 overflow-hidden">
                                    <div className={`h-full rounded-full bg-gradient-to-r ${colors[i % colors.length]} transition-[width] duration-700`}
                                         style={{ width: `${Math.max(pct, 2)}%` }} />
                                </div>
                                <div className="flex justify-between text-xs text-slate-500 mt-1.5">
                                    <span>Selesai {p.done} dari {p.tasks} tugas</span>
                                    {p.end_date && <span>Tenggat {new Date(p.end_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</span>}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function ShipmentChart({ monthly, year }) {
    const max = Math.max(...monthly, 0);
    const top = max > 0 ? Math.ceil(max / 4 / 5) * 5 * 4 || 4 : 4;
    const ticks = [0, 1, 2, 3, 4].map((i) => (top / 4) * i);
    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);
    const onYear = (e) => router.get('/', { year: e.target.value }, { preserveScroll: true, preserveState: true, only: ['overview'] });

    return (
        <div className="card !p-5 h-full animate-slide-up" style={{ animationDelay: '380ms' }}>
            <div className="flex items-center justify-between gap-3 mb-1">
                <h3 className="text-slate-100 font-bold text-lg">Volume Pengiriman (Ton)</h3>
                <label className="relative">
                    <select value={year} onChange={onYear}
                        className="appearance-none text-sm bg-[#fff] border border-[#dbe3ec] rounded-lg pl-3 pr-8 py-1.5 text-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        {years.map((y) => <option key={y} value={y}>Tahun {y}</option>)}
                    </select>
                    <ChevronDownIcon className="w-4 h-4 text-slate-500 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
                </label>
            </div>
            <div className="flex items-center gap-4 text-xs text-slate-400 mb-3">
                <span className="inline-flex items-center gap-1.5"><span className="w-3 h-3 rounded-sm bg-[#22b35e]" /> Realisasi (Surat Jalan)</span>
            </div>
            <div className="flex gap-2 h-[190px]">
                <div className="flex flex-col justify-between text-[11px] text-slate-500 tabular text-right pb-5">
                    {[...ticks].reverse().map((t) => <span key={t}>{nf(t)}</span>)}
                </div>
                <div className="flex-1 relative">
                    <div className="absolute inset-0 bottom-5 flex flex-col justify-between pointer-events-none">
                        {ticks.map((t) => <div key={t} className="border-t border-dashed border-[#e9eef4]" />)}
                    </div>
                    <div className="absolute inset-0 bottom-5 flex items-end justify-between gap-1.5 px-1">
                        {monthly.map((v, i) => (
                            <div key={i} className="flex-1 flex justify-center h-full items-end group relative">
                                <div className="w-full max-w-[18px] rounded-t-md bg-gradient-to-t from-[#16a34a] to-[#34d399] transition-[height] duration-700"
                                     style={{ height: `${top ? (v / top) * 100 : 0}%`, minHeight: v > 0 ? 3 : 0 }}
                                     title={`${MONTHS[i]}: ${nf(v, 2)} ton`} />
                                {v > 0 && (
                                    <span className="absolute -top-6 text-[10px] font-semibold text-slate-200 bg-[#fff] border border-[#e3e9f0] rounded px-1 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                        {nf(v, 2)}
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="absolute inset-x-0 bottom-0 flex justify-between px-1">
                        {MONTHS.map((m) => <span key={m} className="flex-1 text-center text-[11px] text-slate-500">{m}</span>)}
                    </div>
                </div>
            </div>
        </div>
    );
}

function CompositionDonut({ items }) {
    const colors = ['#1e8fe0', '#22b35e', '#f5b52e', '#8b5cf6', '#a3b1c2'];
    const total = items.reduce((s, it) => s + it.value, 0);
    const r = 60;
    const c = 2 * Math.PI * r;
    let acc = 0;
    return (
        <div className="card !p-5 h-full animate-slide-up" style={{ animationDelay: '440ms' }}>
            <h3 className="text-slate-100 font-bold text-lg mb-3">Komposisi Stok per Provinsi</h3>
            {total <= 0 ? (
                <p className="text-sm text-slate-500 py-10 text-center">Belum ada data stok.</p>
            ) : (
                <div className="flex flex-col sm:flex-row items-center gap-5">
                    <div className="relative w-[170px] h-[170px] shrink-0">
                        <svg viewBox="0 0 160 160" className="w-full h-full -rotate-90">
                            <circle cx="80" cy="80" r={r} fill="none" stroke="#eef2f6" strokeWidth="22" />
                            {items.map((it, i) => {
                                const len = (it.value / total) * c;
                                const seg = (
                                    <circle key={it.label} cx="80" cy="80" r={r} fill="none" stroke={colors[i % colors.length]} strokeWidth="22"
                                            strokeDasharray={`${Math.max(len - 2, 0)} ${c}`} strokeDashoffset={-acc}>
                                        <title>{`${it.label}: ${nf(it.value, 2)} ton`}</title>
                                    </circle>
                                );
                                acc += len;
                                return seg;
                            })}
                        </svg>
                        <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <span className="text-xs text-slate-400">Total</span>
                            <span className="text-xl font-bold text-slate-100 tabular leading-tight">{nf(total)}</span>
                            <span className="text-xs text-slate-400">Ton</span>
                        </div>
                    </div>
                    <ul className="flex-1 w-full space-y-2.5">
                        {items.map((it, i) => (
                            <li key={it.label} className="flex items-center gap-2.5 text-sm">
                                <span className="w-3 h-3 rounded-full shrink-0" style={{ background: colors[i % colors.length] }} />
                                <span className="flex-1 truncate text-slate-200">{it.label}</span>
                                <span className="font-semibold text-slate-100 tabular">{nf((it.value / total) * 100, 0)}%</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

function Activity({ items }) {
    const tint = {
        journal: 'bg-[#eef6ff]', invoice: 'bg-[#fff6e6]', faktur: 'bg-[#fff6e6]', kwitansi: 'bg-[#fff6e6]',
        surat_jalan: 'bg-[#e9f9ef]', do: 'bg-[#e9f9ef]', po: 'bg-[#f3eeff]',
    };
    return (
        <div className="card !p-5 h-full animate-slide-up" style={{ animationDelay: '500ms' }}>
            <div className="flex items-center justify-between mb-3">
                <h3 className="text-slate-100 font-bold text-lg">Aktivitas Terbaru</h3>
                <Link href="/documents/log" className="text-[13px] font-medium text-brand-600 hover:text-brand-700">Lihat Semua</Link>
            </div>
            {items.length === 0 ? (
                <p className="text-sm text-slate-500 py-8 text-center">Belum ada aktivitas.</p>
            ) : (
                <ul className="divide-y divide-[#eef2f6]">
                    {items.map((a, i) => (
                        <li key={`${a.kind}-${a.title}-${i}`}>
                            <Link href={a.href} className="flex items-start gap-3 py-2.5 group">
                                <span className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 ${tint[a.type] || 'bg-[#eef6ff]'}`}>
                                    {a.kind === 'journal' ? <JournalColorIcon className="w-5 h-5" /> : <DocumentColorIcon className="w-5 h-5" />}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold text-slate-100 truncate group-hover:text-brand-600">
                                        {DOC_LABEL[a.type] || a.type} {a.title}
                                    </span>
                                    <span className="block text-xs text-slate-500 truncate">
                                        {a.sub || '—'}{a.status ? ` · ${STATUS_LABEL[a.status] || a.status}` : ''}
                                    </span>
                                </span>
                                <span className="text-[11px] text-slate-500 shrink-0 pt-0.5">{waktu(a.at)}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function QuickActions() {
    const perms = usePage().props.auth?.permissions ?? [];
    const actions = [
        { label: 'Buat DO',          href: '/documents/create?type=do',          perm: 'letters.create', icon: <DocumentColorIcon className="w-7 h-7" /> },
        { label: 'Buat Surat Jalan', href: '/documents/create?type=surat_jalan', perm: 'letters.create', icon: <ArchiveColorIcon className="w-7 h-7" /> },
        { label: 'Buat Invoice',     href: '/documents/create?type=invoice',     perm: 'letters.create', icon: <DocumentColorIcon className="w-7 h-7" /> },
        { label: 'Cek Stok Sumber',  href: '/palm-oil-sources',                  perm: 'inventory.view', icon: <PalmPlantationIcon className="w-7 h-7" /> },
        { label: 'Laporan Keuangan', href: '/books/profit-loss',                 perm: 'books.reports.view', icon: <ReportColorIcon className="w-7 h-7" /> },
        { label: 'Jurnal Umum',      href: '/books/journal',                     perm: 'books.journal.view', icon: <JournalColorIcon className="w-7 h-7" /> },
        { label: 'Master Vendor',    href: '/books/vendors',                     perm: 'books.vendors.view', icon: <VendorColorIcon className="w-7 h-7" /> },
        { label: 'Manajemen Tugas',  href: '/tasks',                             perm: 'tasks.view', icon: <TaskBoardColorIcon className="w-7 h-7" /> },
    ].filter((a) => perms.includes(a.perm));

    if (actions.length === 0) return null;
    return (
        <div className="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-3 animate-slide-up" style={{ animationDelay: '560ms' }}>
            {actions.map((a) => (
                <Link key={a.href} href={a.href}
                    className="card !p-3.5 card-hover flex items-center gap-3 group">
                    <span className="w-11 h-11 rounded-xl bg-[#f6f9fc] flex items-center justify-center shrink-0 transition-transform group-hover:scale-110">
                        {a.icon}
                    </span>
                    <span className="text-[13px] font-medium text-slate-200 leading-tight">{a.label}</span>
                </Link>
            ))}
        </div>
    );
}

function Hero({ canSeeSources }) {
    return (
        <div className="relative overflow-hidden rounded-[22px] min-h-[210px] lg:min-h-[250px] mb-5 animate-fade-in
                        bg-gradient-to-br from-[#0b4f7a] via-[#0f7f9f] to-[#1d9a63] text-white">
            {/* Ilustrasi latar: cerobong, tumpukan biomassa, daun */}
            <svg viewBox="0 0 800 260" preserveAspectRatio="xMaxYMax slice" className="absolute inset-0 w-full h-full" aria-hidden="true">
                <defs>
                    <linearGradient id="gepFade" x1="0" x2="1">
                        <stop offset="0" stopColor="#0b4f7a" stopOpacity="1" />
                        <stop offset="0.45" stopColor="#0b4f7a" stopOpacity="0.55" />
                        <stop offset="1" stopColor="#0b4f7a" stopOpacity="0" />
                    </linearGradient>
                </defs>
                <g opacity="0.55">
                    {[440, 470, 520, 560, 610, 655].map((x, i) => (
                        <g key={x}>
                            <rect x={x} y={70 + (i % 3) * 18} width="14" height={190 - (i % 3) * 18} fill="#cfe3ee" />
                            <rect x={x} y={84 + (i % 3) * 18} width="14" height="6" fill="#e05252" />
                            <ellipse cx={x + 7} cy={58 + (i % 3) * 18} rx="16" ry="9" fill="#ffffff" opacity="0.35" />
                        </g>
                    ))}
                    <rect x="420" y="170" width="330" height="90" fill="#9fc3d6" />
                    {[0, 1, 2, 3, 4, 5, 6, 7].map((i) => <rect key={i} x={436 + i * 38} y="188" width="20" height="14" fill="#5fa6c7" />)}
                </g>
                <path d="M360 260 C420 200 470 186 530 196 C600 206 640 170 700 176 C760 182 790 214 800 230 L800 260Z" fill="#7a5532" />
                <path d="M450 260 C500 222 560 214 610 222 C670 232 720 214 800 236 L800 260Z" fill="#5b3d22" />
                {Array.from({ length: 40 }).map((_, i) => (
                    <circle key={i} cx={470 + ((i * 37) % 320)} cy={226 + ((i * 13) % 30)} r={2 + (i % 3)} fill="#a87b4f" opacity="0.9" />
                ))}
                <rect width="800" height="260" fill="url(#gepFade)" />
            </svg>

            <div className="relative z-10 p-6 sm:p-8 lg:p-10 max-w-xl">
                <p className="text-[13px] font-medium tracking-[0.12em] uppercase text-[#fff]/80">PT Geosys Energi Prima</p>
                <h2 className="mt-2 text-[28px] sm:text-[34px] lg:text-[40px] font-extrabold leading-[1.08] drop-shadow-sm">
                    Solusi Energi Biomassa<br />untuk Masa Depan
                </h2>
                <p className="mt-3 text-[15px] text-[#fff]/85 max-w-md">Dari sumber terbaik, untuk energi yang lebih bersih dan berkelanjutan.</p>
                {canSeeSources && (
                    <Link href="/palm-oil-sources"
                        className="inline-flex items-center gap-2 mt-5 px-5 h-11 rounded-full bg-gradient-to-r from-[#22b35e] to-[#16a34a] text-white text-sm font-semibold shadow-[0_10px_20px_-8px_rgb(22_163_74_/_0.8)] hover:brightness-110 transition">
                        Lihat Detail <ArrowRightIcon className="w-4 h-4" />
                    </Link>
                )}
            </div>

            <ul className="hidden xl:flex flex-col gap-3 absolute right-6 top-1/2 -translate-y-1/2 z-10">
                {['Biomassa Berkualitas', 'Rantai Pasok Terintegrasi', 'Mendukung Energi Bersih'].map((t) => (
                    <li key={t} className="flex items-center gap-3 pl-2 pr-5 py-2 rounded-full bg-[#0b2f45]/45 backdrop-blur-md border border-[#fff]/15 text-sm font-medium">
                        <span className="w-9 h-9 rounded-full bg-gradient-to-br from-[#34d399] to-[#16a34a] flex items-center justify-center text-white">
                            <svg viewBox="0 0 24 24" className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                <path d="M5 19c0-8 6-13 14-14-1 8-6 14-14 14z" /><path d="M5 19l7-7" />
                            </svg>
                        </span>
                        {t}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function Overview({ overview, summary = {} }) {
    const perms = usePage().props.auth?.permissions ?? [];
    const o = overview;

    return (
        <div className="mb-8">
            <Hero canSeeSources={perms.includes('inventory.view')} />

            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
                <Kpi delay={60} label="Proyek Aktif" value={nf(o.active_projects)} unit={`/ ${nf(o.total_projects)}`}
                     note="Proyek berjalan" href="/tasks"
                     tint="bg-[#e8f3ff]" icon={<TaskBoardColorIcon className="w-8 h-8" />} />
                <Kpi delay={120} label={`Realisasi Pengiriman ${o.year}`} value={nf(o.shipment_tons, 2)} unit="Ton"
                     trend={o.shipment_trend} note="Dari Surat Jalan" href="/documents?type=surat_jalan"
                     tint="bg-[#e7f8ee]" icon={<ArchiveColorIcon className="w-8 h-8" />} />
                <Kpi delay={180} label={`Nilai Penjualan ${o.year}`} value={rupiahShort(o.sales)}
                     trend={o.sales_trend} note="Jurnal posted" href="/books/profit-loss"
                     tint="bg-[#eef4ff]" icon={<ReportColorIcon className="w-8 h-8" />} />
                <Kpi delay={240} label="Saldo Stok Sumber" value={nf(summary.total_volume, 2)} unit="Ton"
                     note={summary.low_stock_count > 0 ? `${nf(summary.low_stock_count)} sumber stok rendah` : 'Semua stok aman'}
                     href="/palm-oil-sources"
                     tint="bg-[#eef1ff]" icon={<PalmPlantationIcon className="w-8 h-8" />} />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-5">
                <div className="xl:col-span-2"><ProcessFlow summary={summary} /></div>
                <ProjectProgress projects={o.projects || []} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-4 mb-5">
                <ShipmentChart monthly={o.monthly_tons || []} year={o.year} />
                <CompositionDonut items={o.composition || []} />
                <div className="lg:col-span-2 2xl:col-span-1"><Activity items={o.activity || []} /></div>
            </div>

            <QuickActions />
        </div>
    );
}
