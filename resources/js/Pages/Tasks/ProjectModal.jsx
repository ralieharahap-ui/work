import { useState } from 'react';
import { router } from '@inertiajs/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

export default function ProjectModal({ onClose, initialData, team }) {
    const isNew = !initialData;
    const [formData, setFormData] = useState({
        title: initialData?.title || '',
        description: initialData?.description || '',
        status: initialData?.status || 'Planning',
        owner_id: initialData?.owner_id || team[0]?.id || '',
        end_date: initialData?.end_date ? initialData.end_date.substring(0, 10) : '',
    });
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (e) => setFormData({ ...formData, [e.target.name]: e.target.value });

    const handleSubmit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        const opts = { preserveScroll: true, onSuccess: () => onClose(), onFinish: () => setSubmitting(false) };
        if (isNew) {
            router.post(route('task-projects.store'), formData, opts);
        } else {
            router.put(route('task-projects.update', initialData.id), formData, opts);
        }
    };

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-notion-deep w-full max-w-lg overflow-hidden flex flex-col">
                <div className="px-6 py-4 border-b border-black/10 flex justify-between items-center bg-warm-white">
                    <h2 className="text-lg font-bold text-[rgba(0,0,0,0.95)]">{isNew ? 'Buat Proyek Baru' : 'Edit Proyek'}</h2>
                    <button onClick={onClose} className="text-warm-300 hover:text-black/70"><XMarkIcon className="w-5 h-5" /></button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Nama Proyek *</label>
                        <input required type="text" name="title" value={formData.title} onChange={handleChange}
                            className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue focus:ring-1 focus:ring-notion-blue"
                            placeholder="Cth: Ekspansi Vendor Biomassa" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Tujuan / Deskripsi</label>
                        <textarea name="description" value={formData.description} onChange={handleChange} rows="3"
                            className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue focus:ring-1 focus:ring-notion-blue" />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Status Proyek</label>
                            <select name="status" value={formData.status} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue">
                                <option value="Planning">Planning</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="On Hold">On Hold</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Target Selesai</label>
                            <input type="date" name="end_date" value={formData.end_date} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-[rgba(0,0,0,0.75)] mb-1">Project Owner</label>
                        <select name="owner_id" value={formData.owner_id} onChange={handleChange} className="w-full p-2 border border-black/10 rounded-md outline-none focus:border-notion-blue">
                            {team.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </select>
                    </div>
                </form>

                <div className="px-6 py-4 border-t border-black/10 bg-warm-white flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-[rgba(0,0,0,0.75)] bg-white border border-black/10 rounded-md hover:bg-warm-white">Batal</button>
                    <button type="button" onClick={handleSubmit} disabled={submitting} className="px-4 py-2 text-sm font-medium text-white bg-notion-blue rounded-md hover:bg-notion-blue-active disabled:opacity-50">
                        {submitting ? 'Menyimpan...' : 'Simpan Proyek'}
                    </button>
                </div>
            </div>
        </div>
    );
}
