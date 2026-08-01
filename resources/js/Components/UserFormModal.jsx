import { useState } from 'react';
import { router } from '@inertiajs/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

const ROLE_LABELS = {
    super_admin: 'Super Admin (Owner) — akses penuh & kelola pengguna',
    approval: 'Manajer / Approval — kelola proyek & menyetujui',
    reviewer: 'Reviewer / Supervisor — kelola & mengedit task',
    drafter: 'Anggota Tim (Member) — buat & kelola task',
    external: 'Viewer — hanya melihat (read-only)',
};

const FORM_ID = 'user-form-modal';

/**
 * Modal terpusat untuk membuat/mengedit pengguna, dipakai oleh Tasks TeamView
 * dan halaman Admin/Users. Aksi CRUD selalu lewat route admin.users.* (khusus super_admin).
 */
export default function UserFormModal({ onClose, initialData, divisions, roles, theme = 'notion' }) {
    const isNew = !initialData;
    const [formData, setFormData] = useState({
        name: initialData?.name || '',
        email: initialData?.email || '',
        password: '',
        division_id: initialData?.division_id || '',
        role: initialData?.role || roles?.[0] || 'drafter',
    });
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (e) => setFormData({ ...formData, [e.target.name]: e.target.value });

    const handleSubmit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        const opts = { preserveScroll: true, onSuccess: () => onClose(), onFinish: () => setSubmitting(false) };
        if (isNew) {
            router.post(route('admin.users.store'), formData, opts);
        } else {
            router.put(route('admin.users.update', initialData.id), formData, opts);
        }
    };

    const light = theme === 'notion';
    const cls = light
        ? {
            overlay: 'fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4',
            panel: 'bg-white rounded-xl shadow-notion-deep w-full max-w-sm overflow-hidden flex flex-col',
            header: 'px-6 py-4 border-b border-black/10 flex justify-between items-center bg-warm-white',
            title: 'text-lg font-bold text-[rgba(0,0,0,0.95)]',
            closeBtn: 'text-warm-300 hover:text-black/70',
            body: 'p-6 space-y-4',
            label: 'block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1',
            input: 'w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue focus:ring-1 focus:ring-notion-blue',
            hint: 'text-[11px] text-warm-500 mt-1',
            footer: 'px-6 py-4 border-t border-black/10 bg-warm-white flex justify-end gap-2',
            btnSecondary: 'px-4 py-2 text-sm font-medium text-[rgba(0,0,0,0.75)] bg-white border border-black/10 rounded-md hover:bg-warm-white',
            btnPrimary: 'px-4 py-2 text-sm font-medium text-white bg-notion-blue rounded-md hover:bg-notion-blue-active disabled:opacity-50',
        }
        : {
            overlay: 'fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4',
            panel: 'card w-full max-w-sm',
            header: 'flex justify-between items-center mb-4',
            title: 'text-white font-semibold',
            closeBtn: 'text-slate-400 hover:text-white',
            body: 'space-y-4',
            label: 'block text-sm font-medium text-slate-300 mb-1',
            input: 'input w-full',
            hint: 'text-[11px] text-slate-500 mt-1',
            footer: 'flex gap-2 pt-4',
            btnSecondary: 'btn-secondary flex-1',
            btnPrimary: 'btn-primary flex-1',
        };

    return (
        <div className={cls.overlay} onClick={onClose}>
            <div className={cls.panel} onClick={(e) => e.stopPropagation()}>
                <div className={cls.header}>
                    <h2 className={cls.title}>{isNew ? 'Tambah Pengguna' : 'Edit Pengguna'}</h2>
                    <button onClick={onClose} className={cls.closeBtn}><XMarkIcon className="w-5 h-5" /></button>
                </div>

                <form id={FORM_ID} onSubmit={handleSubmit} className={cls.body}>
                    <div>
                        <label className={cls.label}>Nama Lengkap *</label>
                        <input required type="text" name="name" value={formData.name} onChange={handleChange} className={cls.input} placeholder="Nama pengguna" />
                    </div>
                    <div>
                        <label className={cls.label}>Email *</label>
                        <input required type="email" name="email" value={formData.email} onChange={handleChange} className={cls.input} placeholder="nama@pt-gep.com" />
                    </div>
                    <div>
                        <label className={cls.label}>Divisi</label>
                        <select name="division_id" value={formData.division_id} onChange={handleChange} className={cls.input}>
                            <option value="">-- Tidak ditentukan --</option>
                            {(divisions || []).map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={cls.label}>{isNew ? 'Password Login *' : 'Password Baru (opsional)'}</label>
                        <input
                            required={isNew}
                            type="text"
                            name="password"
                            value={formData.password}
                            onChange={handleChange}
                            className={cls.input}
                            placeholder={isNew ? 'Minimal 6 karakter' : 'Kosongkan bila tidak diubah'}
                            minLength={6}
                        />
                        <p className={cls.hint}>{isNew ? 'Berikan password ini kepada pengguna agar bisa login.' : 'Isi hanya jika ingin mereset password pengguna ini.'}</p>
                    </div>
                    <div>
                        <label className={cls.label}>Peran (Role Hak Akses) *</label>
                        <select name="role" value={formData.role} onChange={handleChange} className={cls.input}>
                            {(roles || []).map((r) => <option key={r} value={r}>{ROLE_LABELS[r] || r}</option>)}
                        </select>
                    </div>
                </form>

                <div className={cls.footer}>
                    <button type="button" onClick={onClose} className={cls.btnSecondary}>Batal</button>
                    <button type="submit" form={FORM_ID} disabled={submitting} className={cls.btnPrimary}>
                        {submitting ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    );
}
