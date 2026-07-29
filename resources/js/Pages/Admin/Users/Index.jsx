import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import { CheckIcon, XMarkIcon, ClockIcon } from '@heroicons/react/24/outline';

const tgl = (d) => d ? new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const hierarchyLabel = {
    staff: 'Staff', manager: 'Manager', director: 'Director',
    stakeholder: 'Stakeholder', administrator: 'Administrator',
};

export default function AdminUsersIndex({ users, filters, pendingCount }) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [confirmAction, setConfirmAction] = useState(null); // { user, type: 'activate'|'deactivate' }

    const applyFilter = (status) => {
        router.get(route('admin.users.index'), { ...filters, status }, { preserveState: true, replace: true });
    };

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.users.index'), { ...filters, search }, { preserveState: true, replace: true });
    };

    const runAction = () => {
        if (!confirmAction) return;
        const { user, type } = confirmAction;
        const routeName = type === 'activate' ? 'admin.users.activate' : 'admin.users.deactivate';
        router.patch(route(routeName, user.id), {}, { preserveScroll: true, onFinish: () => setConfirmAction(null) });
    };

    return (
        <>
            <Head title="Manajemen User" />
            <AppLayout title="Manajemen User">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <div>
                        <p className="text-slate-300 text-sm font-medium">Persetujuan &amp; Manajemen Akun</p>
                        <p className="text-slate-500 text-xs mt-0.5">{users.total} pengguna terdaftar</p>
                    </div>
                    {pendingCount > 0 && (
                        <span className="badge badge-amber">
                            <ClockIcon className="w-3.5 h-3.5" /> {pendingCount} menunggu persetujuan
                        </span>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div className="flex gap-1.5">
                        {[
                            { key: undefined, label: 'Semua' },
                            { key: 'pending', label: 'Menunggu' },
                            { key: 'active', label: 'Aktif' },
                        ].map((t) => (
                            <button
                                key={t.label}
                                onClick={() => applyFilter(t.key)}
                                className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors
                                    ${(filters?.status ?? undefined) === t.key
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'}`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                    <form onSubmit={submitSearch} className="flex gap-2">
                        <input
                            className="input !w-56"
                            placeholder="Cari nama / email..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </form>
                </div>

                <div className="card !p-0 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full md:min-w-[820px] table-stack">
                            <thead>
                                <tr className="bg-slate-900/40 border-b border-slate-700/70">
                                    <th className="table-header">Nama</th>
                                    <th className="table-header">Divisi</th>
                                    <th className="table-header">Jenjang</th>
                                    <th className="table-header">Role</th>
                                    <th className="table-header">Terdaftar</th>
                                    <th className="table-header">Status</th>
                                    <th className="table-header text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-12 text-center text-slate-500 text-sm">
                                            Tidak ada pengguna yang cocok.
                                        </td>
                                    </tr>
                                )}
                                {users.data.map((u) => (
                                    <tr key={u.id} className="border-b border-slate-800/70 last:border-0 hover:bg-slate-700/20 transition-colors">
                                        <td className="table-cell font-medium text-white">
                                            {u.name}
                                            <p className="text-slate-500 text-xs font-normal">{u.email}</p>
                                        </td>
                                        <td data-label="Divisi" className="table-cell text-slate-400">{u.division?.name ?? '—'}</td>
                                        <td data-label="Jenjang" className="table-cell text-slate-400">{hierarchyLabel[u.hierarchy] ?? u.hierarchy ?? '—'}</td>
                                        <td data-label="Role" className="table-cell text-slate-400">
                                            {(u.roles ?? []).map((r) => (
                                                <span key={r.id} className="badge badge-slate mr-1 capitalize">{r.name.replace('_', ' ')}</span>
                                            ))}
                                        </td>
                                        <td data-label="Terdaftar" className="table-cell text-slate-400 text-xs whitespace-nowrap">{tgl(u.created_at)}</td>
                                        <td data-label="Status" className="table-cell">
                                            <span className={`badge ${u.is_active ? 'badge-green' : 'badge-amber'}`}>
                                                {u.is_active ? 'Aktif' : 'Menunggu'}
                                            </span>
                                        </td>
                                        <td data-label="Aksi" className="table-cell">
                                            <div className="flex items-center justify-end gap-1">
                                                {!u.is_active && (
                                                    <button
                                                        onClick={() => setConfirmAction({ user: u, type: 'activate' })}
                                                        className="p-1.5 rounded-md text-slate-400 hover:text-emerald-300 hover:bg-slate-700/50 transition-colors"
                                                        title="Aktifkan"
                                                    >
                                                        <CheckIcon className="w-4 h-4" />
                                                    </button>
                                                )}
                                                {u.is_active && (
                                                    <button
                                                        onClick={() => setConfirmAction({ user: u, type: 'deactivate' })}
                                                        className="p-1.5 rounded-md text-slate-400 hover:text-red-400 hover:bg-slate-700/50 transition-colors"
                                                        title="Nonaktifkan"
                                                    >
                                                        <XMarkIcon className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {users.links && users.last_page > 1 && (
                    <div className="flex justify-center gap-1 mt-5">
                        {users.links.map((link, idx) => (
                            <Link
                                key={idx}
                                href={link.url || '#'}
                                className={`min-w-[34px] text-center px-3 py-1.5 text-sm rounded-lg transition-colors
                                    ${link.active ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'}
                                    ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}

                {confirmAction && (
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in"
                         onClick={() => setConfirmAction(null)}>
                        <div className="card w-full max-w-sm" onClick={(e) => e.stopPropagation()}>
                            <div className="flex items-start gap-3">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ring-1 ring-inset
                                    ${confirmAction.type === 'activate' ? 'bg-emerald-500/15 ring-emerald-500/20' : 'bg-red-500/15 ring-red-500/20'}`}>
                                    {confirmAction.type === 'activate'
                                        ? <CheckIcon className="w-5 h-5 text-emerald-400" />
                                        : <XMarkIcon className="w-5 h-5 text-red-400" />}
                                </div>
                                <div>
                                    <h2 className="text-white font-semibold">
                                        {confirmAction.type === 'activate' ? 'Aktifkan akun?' : 'Nonaktifkan akun?'}
                                    </h2>
                                    <p className="text-slate-400 text-sm mt-1">
                                        <span className="text-slate-200">{confirmAction.user.name}</span>
                                        {confirmAction.type === 'activate'
                                            ? ' akan bisa masuk ke aplikasi.'
                                            : ' tidak akan bisa masuk sampai diaktifkan kembali.'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-3 pt-5">
                                <button
                                    onClick={runAction}
                                    className={confirmAction.type === 'activate' ? 'btn-primary flex-1' : 'btn-danger flex-1'}
                                >
                                    Ya, {confirmAction.type === 'activate' ? 'Aktifkan' : 'Nonaktifkan'}
                                </button>
                                <button onClick={() => setConfirmAction(null)} className="btn-secondary flex-1">Batal</button>
                            </div>
                        </div>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
