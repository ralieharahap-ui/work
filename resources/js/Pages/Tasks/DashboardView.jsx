import { ExclamationTriangleIcon, ClockIcon, FolderOpenIcon, UsersIcon, Squares2X2Icon } from '@heroicons/react/24/outline';
import { STATUS_BADGE, fmtDate } from './constants';

function StatCard({ title, value, icon: Icon, danger }) {
    return (
        <div className={`bg-white p-5 rounded-xl border shadow-notion flex items-center justify-between ${danger ? 'border-red-200 bg-red-50/30' : 'border-black/10'}`}>
            <div>
                <p className="text-sm text-warm-500 font-medium mb-1">{title}</p>
                <h3 className={`text-2xl font-bold ${danger ? 'text-red-600' : 'text-[rgba(0,0,0,0.95)]'}`}>{value}</h3>
            </div>
            <div className="p-3 bg-warm-white border border-black/10 rounded-lg">
                <Icon className={`w-5 h-5 ${danger ? 'text-red-500' : 'text-notion-blue'}`} />
            </div>
        </div>
    );
}

export default function DashboardView({ tasks, projects, team }) {
    const activeTasks = tasks.filter((t) => t.status !== 'Done');
    const overdueTasks = activeTasks.filter((t) => t.is_overdue);
    const activeProjects = projects.filter((p) => p.status === 'Ongoing');

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <StatCard title="Task Aktif" value={activeTasks.length} icon={Squares2X2Icon} />
                <StatCard title="Task Kadaluarsa" value={overdueTasks.length} icon={ExclamationTriangleIcon} danger={overdueTasks.length > 0} />
                <StatCard title="Proyek Berjalan" value={activeProjects.length} icon={FolderOpenIcon} />
                <StatCard title="Total Anggota" value={team.length} icon={UsersIcon} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-white p-5 rounded-xl border border-black/10 shadow-notion">
                    <h3 className="font-bold text-[rgba(0,0,0,0.9)] mb-4 flex items-center gap-2">
                        <UsersIcon className="w-[18px] h-[18px] text-warm-500" /> Beban Kerja per Anggota
                    </h3>
                    <div className="space-y-4">
                        {team.map((member) => {
                            const memberTasks = activeTasks.filter((t) => t.pic_id === member.id);
                            return (
                                <div key={member.id} className="flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <div className="w-8 h-8 rounded-full bg-warm-white flex items-center justify-center font-medium text-warm-500 text-sm shrink-0">
                                            {member.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-[rgba(0,0,0,0.85)] truncate">{member.name}</p>
                                            <p className="text-[10px] text-warm-300 uppercase tracking-wide truncate">{member.division || '-'}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3 w-1/3 justify-end shrink-0">
                                        <div className="w-24 bg-warm-white rounded-full h-2 hidden sm:block">
                                            <div className="bg-notion-blue h-2 rounded-full" style={{ width: `${Math.min((memberTasks.length / 10) * 100, 100)}%` }} />
                                        </div>
                                        <span className="text-sm font-medium text-[rgba(0,0,0,0.8)] w-6 text-right">{memberTasks.length}</span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div className="bg-white p-5 rounded-xl border border-black/10 shadow-notion">
                    <h3 className="font-bold text-[rgba(0,0,0,0.9)] mb-4 flex items-center gap-2">
                        <ClockIcon className="w-[18px] h-[18px] text-warm-500" /> Task Mendekati Deadline
                    </h3>
                    <div className="space-y-3">
                        {activeTasks
                            .filter((t) => t.deadline)
                            .sort((a, b) => new Date(a.deadline) - new Date(b.deadline))
                            .slice(0, 5)
                            .map((task) => (
                                <div key={task.id} className="flex items-center justify-between p-3 border border-black/10 rounded-lg hover:bg-warm-white transition-colors">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-[rgba(0,0,0,0.9)] truncate">{task.title}</p>
                                        <p className={`text-xs ${task.is_overdue ? 'text-red-500 font-medium' : 'text-warm-500'}`}>{fmtDate(task.deadline)}</p>
                                    </div>
                                    <span className={`text-[10px] px-2 py-1 rounded-md font-semibold uppercase tracking-wider shrink-0 ml-2 ${STATUS_BADGE[task.status]}`}>
                                        {task.status}
                                    </span>
                                </div>
                            ))}
                        {activeTasks.filter((t) => t.deadline).length === 0 && (
                            <p className="text-sm text-warm-300 text-center py-4">Tidak ada task dengan deadline dekat.</p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
