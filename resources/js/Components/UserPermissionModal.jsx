import { useState } from 'react';
import { router } from '@inertiajs/react';
import { XMarkIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';

/**
 * Modal Super Admin untuk mengatur permission granular per-user secara langsung
 * (tambahan di luar role default-nya) — dipakai di halaman Admin/Users.
 */
export default function UserPermissionModal({ onClose, user, groups }) {
    const [checked, setChecked] = useState(new Set(user.direct_permissions ?? []));
    const [submitting, setSubmitting] = useState(false);

    const toggle = (name) => {
        setChecked((prev) => {
            const next = new Set(prev);
            next.has(name) ? next.delete(name) : next.add(name);
            return next;
        });
    };

    const submit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        router.patch(route('admin.users.permissions.update', user.id), { permissions: Array.from(checked) }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" onClick={onClose}>
            <div className="card w-full max-w-lg max-h-[85vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
                <div className="flex justify-between items-center mb-1">
                    <h2 className="text-white font-semibold flex items-center gap-2">
                        <ShieldCheckIcon className="w-5 h-5 text-blue-400" /> Hak Akses Detail
                    </h2>
                    <button onClick={onClose} className="text-slate-400 hover:text-white"><XMarkIcon className="w-5 h-5" /></button>
                </div>
                <p className="text-slate-500 text-xs mb-4">{user.name} — permission tambahan di luar hak akses bawaan role-nya. Berpengaruh langsung ke menu &amp; fitur yang tampil untuk pengguna ini.</p>

                <form id="user-permission-form" onSubmit={submit} className="space-y-4 overflow-y-auto flex-1 pr-1">
                    {Object.entries(groups).map(([group, perms]) => (
                        <div key={group}>
                            <p className="text-[10px] font-semibold uppercase tracking-widest text-slate-500 mb-2">{group}</p>
                            <div className="space-y-1.5">
                                {Object.entries(perms).map(([name, label]) => (
                                    <label key={name} className="flex items-center gap-2 text-sm text-slate-300 cursor-pointer hover:text-white">
                                        <input
                                            type="checkbox"
                                            checked={checked.has(name)}
                                            onChange={() => toggle(name)}
                                            className="rounded border-slate-600 bg-slate-800 text-blue-500 focus:ring-blue-500/50"
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}
                </form>

                <div className="flex gap-2 pt-4 shrink-0">
                    <button type="button" onClick={onClose} className="btn-secondary flex-1">Batal</button>
                    <button type="submit" form="user-permission-form" disabled={submitting} className="btn-primary flex-1">
                        {submitting ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    );
}
