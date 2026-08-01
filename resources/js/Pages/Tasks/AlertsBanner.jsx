import { useState } from 'react';
import { ExclamationTriangleIcon, ClockIcon, FireIcon, ChevronDownIcon } from '@heroicons/react/24/outline';
import { fmtDate } from './constants';

function AlertGroup({ title, icon: Icon, items, tone, onOpenTask }) {
    const [open, setOpen] = useState(false);
    if (!items?.length) return null;

    const tones = {
        red: 'bg-red-50 border-red-200 text-red-700',
        amber: 'bg-orange-50 border-orange-200 text-orange-700',
        blue: 'bg-notion-blue-badge-bg border-blue-200 text-notion-blue-badge-text',
    };

    return (
        <div className={`rounded-xl border ${tones[tone]} overflow-hidden`}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="w-full flex items-center justify-between gap-2 px-3.5 py-2.5 text-left"
            >
                <span className="flex items-center gap-2 text-sm font-semibold">
                    <Icon className="w-4 h-4" /> {title}
                    <span className="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full bg-white/70 text-xs font-bold">
                        {items.length}
                    </span>
                </span>
                <ChevronDownIcon className={`w-4 h-4 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div className="bg-white/70 border-t border-black/10 divide-y divide-black/5 max-h-56 overflow-y-auto">
                    {items.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => onOpenTask(t)}
                            className="w-full text-left px-3.5 py-2 text-xs hover:bg-white transition-colors flex items-center justify-between gap-2"
                        >
                            <span className="truncate text-[rgba(0,0,0,0.85)] font-medium">{t.title}</span>
                            <span className="shrink-0 text-warm-500">{fmtDate(t.deadline) ?? t.priority}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function AlertsBanner({ alerts, onOpenTask }) {
    if (!alerts || alerts.counts.total === 0) return null;

    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
            <AlertGroup title="Tugas Kadaluarsa" icon={ExclamationTriangleIcon} items={alerts.overdue} tone="red" onOpenTask={onOpenTask} />
            <AlertGroup title="Mendekati Tenggat" icon={ClockIcon} items={alerts.dueSoon} tone="amber" onOpenTask={onOpenTask} />
            <AlertGroup title="Prioritas Tinggi" icon={FireIcon} items={alerts.urgent} tone="blue" onOpenTask={onOpenTask} />
        </div>
    );
}
