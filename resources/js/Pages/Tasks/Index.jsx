import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { PlusIcon, Squares2X2Icon, ChartBarSquareIcon, FolderIcon, UsersIcon } from '@heroicons/react/24/outline';
import AppLayout from '@/Layouts/AppLayout';
import KanbanView from './KanbanView';
import DashboardView from './DashboardView';
import ProjectsView from './ProjectsView';
import TeamView from './TeamView';
import TaskModal from './TaskModal';
import ProjectModal from './ProjectModal';
import CloseTaskModal from './CloseTaskModal';
import AlertsBanner from './AlertsBanner';

const TABS = [
    { key: 'kanban', label: 'Papan Kanban', icon: Squares2X2Icon },
    { key: 'dashboard', label: 'Dashboard', icon: ChartBarSquareIcon },
    { key: 'projects', label: 'Proyek', icon: FolderIcon },
    { key: 'team', label: 'Tim', icon: UsersIcon },
];

export default function TasksIndex({
    tasks, projects, team, divisions, roles, alerts,
    currentUserId, canManageTasks, canManageProjects, canDeleteTasks, canManageUsers,
}) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState('kanban');
    const [editingTask, setEditingTask] = useState(undefined); // undefined = closed
    const [editingProject, setEditingProject] = useState(undefined);
    const [taskToClose, setTaskToClose] = useState(null);

    const handleStatusChange = (task, newStatus) => {
        router.patch(route('tasks.updateStatus', task.id), { status: newStatus }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Manajemen Tugas">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
            </Head>
            <AppLayout title="Manajemen Tugas">
                <div style={{ fontFamily: "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" }}>
                    <div className="bg-white -m-3 sm:-m-5 lg:-m-6 p-4 sm:p-6 min-h-[calc(100vh-4rem)]">
                        <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
                            <div>
                                <h1 className="text-2xl font-bold text-[rgba(0,0,0,0.95)] tracking-tight">Workspace Tugas PT GEP</h1>
                                <p className="text-sm text-warm-500 mt-0.5">Halo, {auth?.user?.name} — kelola dan pantau pekerjaan tim secara real-time.</p>
                            </div>
                            <div className="flex gap-2">
                                {tab === 'kanban' && canManageTasks && (
                                    <button onClick={() => setEditingTask(null)} className="bg-notion-blue hover:bg-notion-blue-active text-white px-4 py-2 rounded-md text-sm font-medium flex items-center gap-2 transition-colors">
                                        <PlusIcon className="w-4 h-4" /> Task Baru
                                    </button>
                                )}
                                {tab === 'projects' && canManageProjects && (
                                    <button onClick={() => setEditingProject(null)} className="bg-black hover:bg-black/80 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center gap-2 transition-colors">
                                        <PlusIcon className="w-4 h-4" /> Proyek Baru
                                    </button>
                                )}
                            </div>
                        </div>

                        <div className="flex gap-1 mb-5 border-b border-black/10 overflow-x-auto">
                            {TABS.map((t) => (
                                <button
                                    key={t.key}
                                    onClick={() => setTab(t.key)}
                                    className={`flex items-center gap-1.5 px-3.5 py-2.5 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap ${
                                        tab === t.key ? 'border-notion-blue text-notion-blue' : 'border-transparent text-warm-500 hover:text-[rgba(0,0,0,0.8)]'
                                    }`}
                                >
                                    <t.icon className="w-4 h-4" /> {t.label}
                                </button>
                            ))}
                        </div>

                        <AlertsBanner alerts={alerts} onOpenTask={(t) => setEditingTask(tasks.find((x) => x.id === t.id) || t)} />

                        {tab === 'kanban' && (
                            <KanbanView
                                tasks={tasks}
                                canEdit={canManageTasks}
                                onEdit={(t) => setEditingTask(t)}
                                onStatusChange={handleStatusChange}
                                onCloseRequest={(t) => setTaskToClose(t)}
                            />
                        )}
                        {tab === 'dashboard' && <DashboardView tasks={tasks} projects={projects} team={team} />}
                        {tab === 'projects' && (
                            <ProjectsView
                                projects={projects}
                                tasks={tasks}
                                canManageProjects={canManageProjects}
                                canDelete={canDeleteTasks}
                                onNew={() => setEditingProject(null)}
                                onEdit={(p) => setEditingProject(p)}
                            />
                        )}
                        {tab === 'team' && (
                            <TeamView
                                team={team}
                                tasks={tasks}
                                currentUserId={currentUserId}
                                canManageUsers={canManageUsers}
                                divisions={divisions}
                                roles={roles}
                            />
                        )}
                    </div>
                </div>

                {editingTask !== undefined && (
                    <TaskModal
                        onClose={() => setEditingTask(undefined)}
                        initialData={editingTask}
                        team={team}
                        projects={projects}
                        divisions={divisions}
                        currentUserId={currentUserId}
                        canEdit={canManageTasks}
                        canDelete={canDeleteTasks}
                        onRequestClose={(t) => { setEditingTask(undefined); setTaskToClose(t); }}
                    />
                )}

                {editingProject !== undefined && (
                    <ProjectModal
                        onClose={() => setEditingProject(undefined)}
                        initialData={editingProject}
                        team={team}
                    />
                )}

                {taskToClose && (
                    <CloseTaskModal task={taskToClose} onClose={() => setTaskToClose(null)} />
                )}
            </AppLayout>
        </>
    );
}
