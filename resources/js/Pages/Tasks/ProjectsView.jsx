import { router } from '@inertiajs/react';
import { PencilSquareIcon, TrashIcon, UsersIcon, CalendarDaysIcon } from '@heroicons/react/24/outline';
import { fmtDate } from './constants';

const PROJECT_BADGE = {
    Ongoing: 'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    Planning: 'bg-purple-50 text-purple-700',
    Closed: 'bg-warm-100 text-warm-500',
    'On Hold': 'bg-amber-50 text-amber-700',
};

export default function ProjectsView({ projects, tasks, canManageProjects, canDelete, onNew, onEdit }) {
    const handleDelete = (project) => {
        if (!window.confirm(`Hapus proyek "${project.title}"? Task terkait tidak akan terhapus.`)) return;
        router.delete(route('task-projects.destroy', project.id), { preserveScroll: true });
    };

    return (
        <div>
            <div className="mb-6 flex justify-between items-center">
                <h3 className="text-lg font-bold text-[rgba(0,0,0,0.9)]">Daftar Proyek Strategis</h3>
                {canManageProjects && (
                    <button onClick={onNew} className="text-sm bg-black text-white px-3 py-1.5 rounded-md hover:bg-black/80 shadow-sm transition-colors">
                        + Buat Proyek
                    </button>
                )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {projects.map((project) => {
                    const projTasks = tasks.filter((t) => t.project_id === project.id);
                    const doneTasks = projTasks.filter((t) => t.status === 'Done');
                    const progress = projTasks.length === 0 ? 0 : Math.round((doneTasks.length / projTasks.length) * 100);

                    return (
                        <div key={project.id} className="bg-white p-5 rounded-xl border border-black/10 shadow-notion flex flex-col hover:shadow-notion-deep transition-shadow">
                            <div className="flex justify-between items-start mb-2">
                                <span className={`text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded ${PROJECT_BADGE[project.status] || 'bg-warm-100 text-warm-500'}`}>
                                    {project.status}
                                </span>
                                {canManageProjects && (
                                    <div className="flex items-center gap-1">
                                        <button onClick={() => onEdit(project)} className="text-warm-300 hover:text-black/70 p-1"><PencilSquareIcon className="w-4 h-4" /></button>
                                        {canDelete && (
                                            <button onClick={() => handleDelete(project)} className="text-warm-300 hover:text-red-500 p-1"><TrashIcon className="w-4 h-4" /></button>
                                        )}
                                    </div>
                                )}
                            </div>

                            <h4 className="font-bold text-lg text-[rgba(0,0,0,0.95)] mb-1 leading-snug">{project.title}</h4>
                            <p className="text-sm text-warm-500 line-clamp-2 mb-4 flex-1">
                                {project.description || <span className="italic text-warm-300">Tidak ada deskripsi</span>}
                            </p>

                            <div className="mt-auto space-y-3 pt-3 border-t border-black/5">
                                <div className="flex justify-between text-xs text-warm-500">
                                    <span className="flex items-center gap-1"><UsersIcon className="w-3 h-3" /> {project.owner?.name || 'No Owner'}</span>
                                    <span className="flex items-center gap-1"><CalendarDaysIcon className="w-3 h-3" /> {project.end_date ? fmtDate(project.end_date) : '-'}</span>
                                </div>
                                <div>
                                    <div className="flex justify-between text-[11px] font-semibold text-[rgba(0,0,0,0.7)] mb-1 uppercase tracking-wide">
                                        <span>Progress Task</span>
                                        <span>{progress}% ({doneTasks.length}/{projTasks.length})</span>
                                    </div>
                                    <div className="w-full bg-warm-white rounded-full h-2">
                                        <div className="bg-green-500 h-2 rounded-full transition-all duration-500" style={{ width: `${progress}%` }} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}
                {projects.length === 0 && (
                    <p className="text-sm text-warm-300 col-span-full text-center py-8">Belum ada proyek strategis.</p>
                )}
            </div>
        </div>
    );
}
