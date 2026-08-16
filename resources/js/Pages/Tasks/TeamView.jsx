import { useState } from 'react';
import { router } from '@inertiajs/react';
import { PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/react/24/outline';
import { ROLE_LABELS } from './constants';
import UserFormModal from '@/Components/UserFormModal';

const ROLE_BADGE = {
    super_admin: 'bg-purple-50 text-purple-700',
    approval: 'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    reviewer: 'bg-amber-50 text-amber-700',
    drafter: 'bg-warm-100 text-warm-500',
    external: 'bg-red-50 text-red-500',
};

export default function TeamView({ team, tasks, currentUserId, canManageUsers, divisions, roles }) {
    const [editingUser, setEditingUser] = useState(undefined); // undefined = closed, null = new, object = edit

    const handleDelete = (member) => {
        if (!window.confirm(`Hapus akses pengguna ${member.name}? Tindakan ini tidak dapat dibatalkan.`)) return;
        router.delete(route('admin.users.destroy', member.id), { preserveScroll: true });
    };

    return (
        <div>
            {canManageUsers && (
                <div className="mb-4 flex justify-end">
                    <button
                        onClick={() => setEditingUser(null)}
                        className="text-sm bg-notion-blue text-white px-3 py-1.5 rounded-md hover:bg-notion-blue-active shadow-sm transition-colors flex items-center gap-1.5"
                    >
                        <PlusIcon className="w-4 h-4" /> Tambah Pengguna
                    </button>
                </div>
            )}

            <div className="bg-white rounded-xl border border-black/10 overflow-hidden shadow-notion">
                <div className="p-4 border-b border-black/10 bg-warm-white">
                    <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Direktori &amp; Akses Pengguna</h3>
                    <p className="text-xs text-warm-500 mt-1">Kelola anggota tim dan hak akses (Role) — khusus Super Admin</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[640px]">
                        <thead>
                            <tr className="bg-white border-b border-black/10 text-sm text-warm-500">
                                <th className="p-4 font-medium">Nama Anggota</th>
                                <th className="p-4 font-medium">Divisi</th>
                                <th className="p-4 font-medium">Peran (Role)</th>
                                <th className="p-4 font-medium text-center">Task Aktif / Selesai</th>
                                {canManageUsers && <th className="p-4 font-medium text-right">Aksi</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-black/5 text-sm">
                            {team.map((member) => {
                                const memberTasks = tasks.filter((t) => t.pic_id === member.id);
                                const active = memberTasks.filter((t) => t.status !== 'Done').length;
                                const done = memberTasks.filter((t) => t.status === 'Done').length;
                                const isSelf = currentUserId === member.id;

                                return (
                                    <tr key={member.id} className="hover:bg-warm-white transition-colors">
                                        <td className="p-4 font-medium text-[rgba(0,0,0,0.9)]">
                                            <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 rounded-full bg-notion-blue-badge-bg text-notion-blue-badge-text flex items-center justify-center font-bold shrink-0">
                                                    {member.name.charAt(0).toUpperCase()}
                                                </div>
                                                <div>
                                                    {member.name}
                                                    {isSelf && <span className="ml-2 text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded uppercase tracking-wider font-bold">Anda</span>}
                                                    {!member.is_active && <span className="ml-2 text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded uppercase tracking-wider font-bold">Nonaktif</span>}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="p-4 text-warm-500">{member.division || '-'}</td>
                                        <td className="p-4">
                                            <span className={`inline-block px-2 py-1 rounded text-xs font-medium ${ROLE_BADGE[member.role] || 'bg-warm-100 text-warm-500'}`}>
                                                {ROLE_LABELS[member.role] || member.role || 'Anggota Tim'}
                                            </span>
                                        </td>
                                        <td className="p-4 text-center">
                                            <span className="text-[rgba(0,0,0,0.85)] font-medium">{active}</span>
                                            <span className="text-warm-300 mx-1">/</span>
                                            <span className="text-warm-500">{done}</span>
                                        </td>
                                        {canManageUsers && (
                                            <td className="p-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button onClick={() => setEditingUser(member)} className="text-notion-blue hover:text-notion-blue-active p-1" title="Edit">
                                                        <PencilSquareIcon className="w-4 h-4" />
                                                    </button>
                                                    {!isSelf && (
                                                        <button onClick={() => handleDelete(member)} className="text-red-500 hover:text-red-700 p-1" title="Hapus akses">
                                                            <TrashIcon className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {editingUser !== undefined && (
                <UserFormModal
                    onClose={() => setEditingUser(undefined)}
                    initialData={editingUser}
                    divisions={divisions}
                    roles={roles}
                    theme="notion"
                />
            )}
        </div>
    );
}
