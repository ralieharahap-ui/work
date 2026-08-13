import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    PlusIcon, SparklesIcon, CircleStackIcon, KeyIcon, WrenchScrewdriverIcon,
} from '@heroicons/react/24/outline';
import AppLayout from '@/Layouts/AppLayout';
import TaskDetail from './TaskDetail';
import MemoryView from './MemoryView';
import IntegrationsView from './IntegrationsView';
import NewTaskModal from './NewTaskModal';
import { STATUS_BADGE, STATUS_LABEL, TASK_TYPE_LABEL, fmtTime, pct } from './constants';

const TABS = [
    { key: 'tasks',        label: 'Pekerjaan',    icon: SparklesIcon },
    { key: 'memory',       label: 'Memori',       icon: CircleStackIcon },
    { key: 'integrations', label: 'Akses Tools',  icon: KeyIcon },
    { key: 'tools',        label: 'Kemampuan',    icon: WrenchScrewdriverIcon },
];

/**
 * Dasbor asisten AI. Prioritasnya keterbukaan: apa yang sedang dikerjakan,
 * atas dasar apa, dengan tool apa, dan apa yang sudah dipelajarinya.
 */
export default function AgentIndex({
    tasks, task, integrations, onboarding, memory, tools, agentName, llmProvider, autonomy, can,
}) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState('tasks');
    const [creating, setCreating] = useState(false);

    const select = (id) => router.get(route('agent.index'), { task: id }, {
        preserveState: true, preserveScroll: true, only: ['task'],
    });

    const waiting = tasks.filter((row) => row.needs_approval).length;
    const pendingAccess = onboarding.pending?.length ?? 0;

    return (
        <>
            <Head title="Asisten AI">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
            </Head>

            <AppLayout title="Asisten AI">
                <div style={{ fontFamily: "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" }}>
                    <div className="bg-white -m-3 sm:-m-5 lg:-m-6 p-4 sm:p-6 min-h-[calc(100vh-4rem)]">

                        <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
                            <div>
                                <h1 className="text-2xl font-bold text-[rgba(0,0,0,0.95)] tracking-tight">{agentName}</h1>
                                <p className="text-sm text-warm-500 mt-0.5">
                                    Halo, {auth?.user?.name} — tuliskan pekerjaan kantor Anda, saya rencanakan, kerjakan,
                                    dan periksa hasilnya.
                                </p>
                                <p className="text-[11px] text-warm-300 mt-1">
                                    Perencana: {llmProvider} · mode otonomi: {autonomy} · {tools.length} kemampuan aktif
                                </p>
                            </div>

                            {can.create && (
                                <button type="button" onClick={() => setCreating(true)}
                                    className="flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg bg-notion-blue text-white hover:opacity-90">
                                    <PlusIcon className="w-4 h-4" /> Tugaskan pekerjaan
                                </button>
                            )}
                        </div>

                        {/* Panduan akses — muncul selama masih ada akses yang belum diberikan */}
                        {pendingAccess > 0 && tab !== 'integrations' && (
                            <button type="button" onClick={() => setTab('integrations')}
                                className="w-full text-left bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 hover:bg-amber-100/70">
                                <p className="text-sm font-semibold text-amber-900">
                                    Saya masih membutuhkan {pendingAccess} akses untuk bekerja penuh
                                </p>
                                <p className="text-xs text-amber-800 mt-0.5">
                                    Klik di sini untuk melihat apa saja yang dibutuhkan, untuk apa dipakai, dan cara memberikannya.
                                </p>
                            </button>
                        )}

                        {waiting > 0 && (
                            <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4">
                                <p className="text-sm font-semibold text-amber-900">
                                    {waiting} pekerjaan menunggu persetujuan Anda sebelum melangkah lebih jauh.
                                </p>
                            </div>
                        )}

                        <div className="flex gap-1 border-b border-black/10 mb-5 overflow-x-auto">
                            {TABS.map(({ key, label, icon: Icon }) => (
                                <button key={key} type="button" onClick={() => setTab(key)}
                                    className={`flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors ${
                                        tab === key
                                            ? 'border-notion-blue text-notion-blue'
                                            : 'border-transparent text-warm-500 hover:text-[rgba(0,0,0,0.8)]'
                                    }`}>
                                    <Icon className="w-4 h-4" /> {label}
                                </button>
                            ))}
                        </div>

                        {tab === 'tasks' && (
                            <div className="grid grid-cols-1 xl:grid-cols-[320px_1fr] gap-4">
                                <div className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                                    <div className="p-3 border-b border-black/10 bg-warm-white">
                                        <h3 className="font-bold text-sm text-[rgba(0,0,0,0.9)]">Daftar pekerjaan</h3>
                                    </div>
                                    <ul className="divide-y divide-black/5 max-h-[70vh] overflow-y-auto">
                                        {tasks.length === 0 && (
                                            <li className="p-6 text-center text-xs text-warm-500">
                                                Belum ada pekerjaan. Mulai dengan tombol “Tugaskan pekerjaan”.
                                            </li>
                                        )}
                                        {tasks.map((row) => (
                                            <li key={row.id}>
                                                <button type="button" onClick={() => select(row.id)}
                                                    className={`w-full text-left p-3 hover:bg-warm-white transition-colors ${
                                                        task?.id === row.id ? 'bg-warm-white' : ''}`}>
                                                    <div className="flex items-start justify-between gap-2">
                                                        <p className="text-sm text-[rgba(0,0,0,0.9)] line-clamp-2">{row.title}</p>
                                                        <span className={`shrink-0 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full ${STATUS_BADGE[row.status]}`}>
                                                            {STATUS_LABEL[row.status] || row.status}
                                                        </span>
                                                    </div>
                                                    <p className="text-[11px] text-warm-300 mt-1">
                                                        {TASK_TYPE_LABEL[row.task_type] || row.task_type} · {fmtTime(row.created_at)}
                                                        {row.confidence?.overall ? ` · keyakinan ${pct(row.confidence.overall)}` : ''}
                                                    </p>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <TaskDetail task={task} can={can} onOpenAccess={() => setTab('integrations')} />
                            </div>
                        )}

                        {tab === 'memory' && <MemoryView memory={memory} />}

                        {tab === 'integrations' && (
                            <IntegrationsView
                                integrations={integrations}
                                onboarding={onboarding}
                                canManage={can.manageAccess}
                                agentName={agentName}
                            />
                        )}

                        {tab === 'tools' && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                                {tools.map((tool) => (
                                    <div key={tool.name} className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="font-semibold text-sm text-[rgba(0,0,0,0.9)]">{tool.title}</p>
                                            <span className={`text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded ${
                                                tool.risk_level === 'high' ? 'bg-red-50 text-red-600'
                                                    : tool.risk_level === 'medium' ? 'bg-amber-50 text-amber-700'
                                                    : 'bg-green-50 text-green-700'}`}>
                                                risiko {tool.risk_level}
                                            </span>
                                        </div>
                                        <code className="text-[11px] text-warm-300">{tool.name}</code>
                                        <p className="text-xs text-warm-500 mt-1.5">{tool.description}</p>
                                        {tool.integration && (
                                            <p className="text-[11px] text-warm-300 mt-2">Butuh akses: {tool.integration}</p>
                                        )}
                                        {tool.permission && (
                                            <p className="text-[11px] text-warm-300">Butuh izin aplikasi: {tool.permission}</p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </AppLayout>

            {creating && <NewTaskModal onClose={() => setCreating(false)} />}
        </>
    );
}
