import { useState } from 'react';
import { router } from '@inertiajs/react';
import { XMarkIcon, CheckIcon, Square2StackIcon, TrashIcon, CheckCircleIcon, PaperClipIcon } from '@heroicons/react/24/outline';
import { CATEGORIES, PRIORITIES, KANBAN_EDITABLE_STATUSES, fmtDate } from './constants';
import CommentThread from './CommentThread';

export default function TaskModal({ onClose, initialData, team, projects, divisions, currentUserId, canEdit, canDelete, onRequestClose }) {
    const isNew = !initialData;

    const [formData, setFormData] = useState(() => ({
        title: initialData?.title || '',
        description: initialData?.description || '',
        category: initialData?.category || 'Operasional',
        division_id: initialData?.division_id || '',
        pic_id: initialData?.pic_id || currentUserId || '',
        status: initialData && initialData.status !== 'Done' ? initialData.status : 'To Do',
        priority: initialData?.priority || 'Medium',
        deadline: initialData?.deadline ? initialData.deadline.substring(0, 10) : '',
        project_id: initialData?.project_id || '',
        subtasks: (initialData?.checklist_items || []).map((s) => ({ id: s.id, text: s.text, is_completed: s.is_completed })),
    }));

    const [newSubtask, setNewSubtask] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (e) => setFormData({ ...formData, [e.target.name]: e.target.value });

    const addSubtask = () => {
        if (!newSubtask.trim()) return;
        setFormData({ ...formData, subtasks: [...formData.subtasks, { text: newSubtask, is_completed: false }] });
        setNewSubtask('');
    };

    const toggleSubtask = (idx) => {
        const subtasks = formData.subtasks.map((s, i) => (i === idx ? { ...s, is_completed: !s.is_completed } : s));
        setFormData({ ...formData, subtasks });
    };

    const removeSubtask = (idx) => setFormData({ ...formData, subtasks: formData.subtasks.filter((_, i) => i !== idx) });

    const handleSubmit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        const payload = { ...formData, subtasks: formData.subtasks };
        const opts = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setSubmitting(false),
        };
        if (isNew) {
            router.post(route('tasks.store'), payload, opts);
        } else {
            router.put(route('tasks.update', initialData.id), payload, opts);
        }
    };

    const handleDelete = () => {
        if (!window.confirm('Hapus task ini secara permanen?')) return;
        router.delete(route('tasks.destroy', initialData.id), { onSuccess: () => onClose() });
    };

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-notion-deep w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
                <div className="px-6 py-4 border-b border-black/10 flex justify-between items-center bg-warm-white">
                    <h2 className="text-lg font-bold text-[rgba(0,0,0,0.95)]">{isNew ? 'Buat Task Baru' : 'Edit Task'}</h2>
                    <button onClick={onClose} className="text-warm-300 hover:text-black/70"><XMarkIcon className="w-5 h-5" /></button>
                </div>

                <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 space-y-5">
                    <fieldset disabled={!canEdit} className="space-y-5">
                        <div>
                            <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Judul Task *</label>
                            <input required type="text" name="title" value={formData.title} onChange={handleChange}
                                className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue focus:ring-1 focus:ring-notion-blue"
                                placeholder="Cth: Rekap DO PKS A" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Deskripsi</label>
                            <textarea name="description" value={formData.description} onChange={handleChange} rows="3"
                                className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue focus:ring-1 focus:ring-notion-blue"
                                placeholder="Detail pekerjaan..." />
                        </div>

                        <div className="bg-warm-white p-4 rounded-lg border border-black/10">
                            <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-2 flex items-center gap-2">
                                <Square2StackIcon className="w-4 h-4" /> Sub-tasks / Checklist
                            </label>
                            <div className="space-y-2 mb-3">
                                {formData.subtasks.map((st, idx) => (
                                    <div key={idx} className="flex items-center justify-between bg-white p-2 border border-black/10 rounded">
                                        <div className="flex items-center gap-3 overflow-hidden">
                                            <button type="button" onClick={() => toggleSubtask(idx)} className={`shrink-0 ${st.is_completed ? 'text-notion-blue' : 'text-warm-300'}`}>
                                                <CheckCircleIcon className="w-[18px] h-[18px]" />
                                            </button>
                                            <span className={`text-sm truncate ${st.is_completed ? 'line-through text-warm-300' : 'text-[rgba(0,0,0,0.8)]'}`}>{st.text}</span>
                                        </div>
                                        <button type="button" onClick={() => removeSubtask(idx)} className="text-warm-300 hover:text-red-500 shrink-0 ml-2">
                                            <TrashIcon className="w-4 h-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                            <div className="flex gap-2">
                                <input type="text" value={newSubtask} onChange={(e) => setNewSubtask(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addSubtask())}
                                    className="flex-1 p-2 text-sm border border-black/10 rounded-md outline-none focus:border-notion-blue"
                                    placeholder="Tambah item baru..." />
                                <button type="button" onClick={addSubtask} className="px-4 py-2 bg-black text-white rounded-md text-sm hover:bg-black/80 transition-colors">
                                    Tambah
                                </button>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Kategori</label>
                                <select name="category" value={formData.category} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue">
                                    {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Prioritas</label>
                                <select name="priority" value={formData.priority} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue">
                                    {PRIORITIES.map((p) => <option key={p} value={p}>{p}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">PIC (Penanggung Jawab)</label>
                                <select name="pic_id" value={formData.pic_id} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue">
                                    <option value="">-- Pilih PIC --</option>
                                    {team.map((t) => <option key={t.id} value={t.id}>{t.name}{t.division ? ` (${t.division})` : ''}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Divisi</label>
                                <select name="division_id" value={formData.division_id} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue">
                                    <option value="">-- Tidak ditentukan --</option>
                                    {divisions.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Status</label>
                                <select name="status" value={formData.status} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue">
                                    {KANBAN_EDITABLE_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Tenggat Waktu</label>
                                <input type="date" name="deadline" value={formData.deadline} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue" />
                            </div>
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Terkait Proyek (Opsional)</label>
                                <select name="project_id" value={formData.project_id} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue">
                                    <option value="">-- Tidak ada proyek --</option>
                                    {projects.map((p) => <option key={p.id} value={p.id}>{p.title}</option>)}
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    {!isNew && initialData.status === 'Done' && initialData.evidence_url && (
                        <div className="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-sm text-green-800">
                                <PaperClipIcon className="w-4 h-4 shrink-0" />
                                <span>Ditutup oleh <strong>{initialData.closer?.name || '-'}</strong> pada {fmtDate(initialData.closed_at)}</span>
                            </div>
                            <a href={initialData.evidence_url} target="_blank" rel="noreferrer" className="text-xs font-semibold text-notion-blue hover:underline shrink-0">
                                Lihat Bukti
                            </a>
                        </div>
                    )}

                    {!isNew && canEdit && initialData.status !== 'Done' && (
                        <button
                            type="button"
                            onClick={() => onRequestClose(initialData)}
                            className="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-green-700 bg-green-50 border border-green-200 rounded-md hover:bg-green-100 transition-colors"
                        >
                            <CheckIcon className="w-4 h-4" /> Selesaikan Task &amp; Unggah Bukti
                        </button>
                    )}

                    {!isNew && <CommentThread task={initialData} />}
                </form>

                <div className="px-6 py-4 border-t border-black/10 bg-warm-white flex justify-between gap-2 shrink-0">
                    <div>
                        {!isNew && canDelete && (
                            <button type="button" onClick={handleDelete} className="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-md hover:bg-red-100">
                                Hapus Task
                            </button>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-[rgba(0,0,0,0.75)] bg-white border border-black/10 rounded-md hover:bg-warm-white">
                            {canEdit ? 'Batal' : 'Tutup'}
                        </button>
                        {canEdit && (
                            <button type="button" onClick={handleSubmit} disabled={submitting} className="px-4 py-2 text-sm font-medium text-white bg-notion-blue rounded-md hover:bg-notion-blue-active disabled:opacity-50">
                                {submitting ? 'Menyimpan...' : 'Simpan Task'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
