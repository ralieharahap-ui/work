import { router } from '@inertiajs/react';
import {
    PlayIcon, PauseIcon, XCircleIcon, ArrowDownTrayIcon, CheckIcon, XMarkIcon,
    ShieldExclamationIcon, KeyIcon,
} from '@heroicons/react/24/outline';
import {
    STATUS_BADGE, STATUS_LABEL, STEP_BADGE, STEP_LABEL, RISK_BADGE,
    TASK_TYPE_LABEL, EVENT_STYLE, fmtTime, pct,
} from './constants';

/**
 * Rincian satu pekerjaan: rencana, langkah, linimasa keputusan, persetujuan,
 * berkas hasil, dan memori yang dipakai. Fokusnya pada keterbacaan jejak —
 * pengguna harus bisa menjawab "kenapa agent melakukan ini?".
 */
export default function TaskDetail({ task, can, onOpenAccess }) {
    if (!task) {
        return (
            <div className="bg-white rounded-xl border border-black/10 shadow-notion p-10 text-center text-warm-500 text-sm">
                Belum ada pekerjaan yang dipilih.
            </div>
        );
    }

    const act = (name) => router.post(route(name, task.id), {}, { preserveScroll: true });

    const decide = (approval, decision) => {
        router.post(route('agent.approvals.decide', approval.id), { decision }, { preserveScroll: true });
    };

    const pending = (task.approvals || []).filter((a) => a.status === 'pending');
    const running = !['COMPLETED', 'FAILED', 'CANCELLED'].includes(task.status);

    return (
        <div className="space-y-4">
            {/* Kepala */}
            <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className={`text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${STATUS_BADGE[task.status]}`}>
                                {STATUS_LABEL[task.status] || task.status}
                            </span>
                            <span className="text-[10px] text-warm-500 uppercase tracking-wider">
                                {TASK_TYPE_LABEL[task.task_type] || task.task_type}
                            </span>
                            <span className="text-[10px] text-warm-300">via {task.source}</span>
                        </div>
                        <h2 className="text-lg font-bold text-[rgba(0,0,0,0.9)] mt-1.5">{task.title}</h2>
                        <p className="text-xs text-warm-500 mt-1 max-w-3xl">{task.objective}</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {running && (
                            <>
                                <button type="button" onClick={() => act('agent.tasks.run')}
                                    className="flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-50">
                                    <PlayIcon className="w-3.5 h-3.5" /> Lanjutkan
                                </button>
                                {task.status === 'PAUSED' ? (
                                    <button type="button" onClick={() => act('agent.tasks.resume')}
                                        className="flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-50">
                                        <PlayIcon className="w-3.5 h-3.5" /> Aktifkan lagi
                                    </button>
                                ) : (
                                    <button type="button" onClick={() => act('agent.tasks.pause')}
                                        className="flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-50">
                                        <PauseIcon className="w-3.5 h-3.5" /> Jeda
                                    </button>
                                )}
                                <button type="button" onClick={() => act('agent.tasks.cancel')}
                                    className="flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">
                                    <XCircleIcon className="w-3.5 h-3.5" /> Batalkan
                                </button>
                            </>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-3 border-t border-black/5">
                    {[
                        ['Keyakinan rencana', pct(task.confidence?.planning)],
                        ['Eksekusi', pct(task.confidence?.execution)],
                        ['Pemeriksaan', pct(task.confidence?.verification)],
                        ['Keyakinan akhir', pct(task.confidence?.overall)],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <p className="text-[10px] uppercase tracking-wider text-warm-300">{label}</p>
                            <p className="text-sm font-semibold text-[rgba(0,0,0,0.9)]">{value}</p>
                        </div>
                    ))}
                </div>
            </div>

            {/* Menunggu persetujuan */}
            {pending.map((approval) => (
                <div key={approval.id} className="bg-amber-50 rounded-xl border border-amber-200 p-4">
                    <div className="flex items-start gap-2">
                        <ShieldExclamationIcon className="w-5 h-5 text-amber-600 shrink-0" />
                        <div className="flex-1 min-w-0">
                            <p className="font-semibold text-sm text-amber-900">Butuh persetujuan Anda</p>
                            <p className="text-xs text-amber-800 mt-1">{approval.summary}</p>
                            <p className="text-xs text-amber-700 mt-1">
                                Tool <code className="bg-amber-100 px-1 rounded">{approval.tool}</code> ·
                                risiko <span className="font-semibold">{approval.risk_level}</span> — {approval.rationale}
                            </p>
                            {approval.payload && (
                                <pre className="mt-2 text-[11px] bg-white/70 rounded-lg p-2 overflow-x-auto text-amber-900">
                                    {JSON.stringify(approval.payload, null, 2)}
                                </pre>
                            )}
                            {can.approve && (
                                <div className="flex gap-2 mt-3">
                                    <button type="button" onClick={() => decide(approval, 'approve')}
                                        className="flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700">
                                        <CheckIcon className="w-3.5 h-3.5" /> Setujui
                                    </button>
                                    <button type="button" onClick={() => decide(approval, 'reject')}
                                        className="flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg border border-amber-300 text-amber-900 hover:bg-amber-100">
                                        <XMarkIcon className="w-3.5 h-3.5" /> Tolak
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            ))}

            {/* Tertahan menunggu akses */}
            {task.blocked_on && (
                <div className="bg-orange-50 rounded-xl border border-orange-200 p-4 flex items-start gap-2">
                    <KeyIcon className="w-5 h-5 text-orange-600 shrink-0" />
                    <div>
                        <p className="font-semibold text-sm text-orange-900">Pekerjaan tertahan — asisten butuh akses</p>
                        <p className="text-xs text-orange-800 mt-1">{task.failure_reason}</p>
                        <button type="button" onClick={onOpenAccess}
                            className="mt-2 text-xs font-semibold text-orange-900 underline">
                            Buka pengaturan Akses Tools
                        </button>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {/* Rencana & langkah */}
                <div className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                    <div className="p-4 border-b border-black/10 bg-warm-white">
                        <h3 className="font-bold text-sm text-[rgba(0,0,0,0.9)]">Rencana kerja</h3>
                        <p className="text-xs text-warm-500 mt-0.5">
                            {task.plan?.strategy || 'Rencana disusun otomatis.'}
                        </p>
                        <p className="text-[11px] text-warm-300 mt-1">
                            Sumber rencana: {task.plan?.origin || '-'} · versi {task.plan_version}
                            {task.replans > 0 && ` · disusun ulang ${task.replans}×`}
                        </p>
                    </div>
                    <ol className="divide-y divide-black/5">
                        {(task.steps || []).map((step) => (
                            <li key={step.id} className="p-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="text-sm text-[rgba(0,0,0,0.9)]">{step.objective}</p>
                                        <p className="text-[11px] text-warm-500 mt-0.5">
                                            <code className="bg-warm-100 px-1 rounded">{step.tool}</code>
                                            {step.attempts > 1 && ` · ${step.attempts}× percobaan`}
                                            {step.risk_level !== 'low' && (
                                                <span className={`ml-1 px-1.5 py-0.5 rounded ${RISK_BADGE[step.risk_level]}`}>
                                                    risiko {step.risk_level}
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <span className={`shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${STEP_BADGE[step.status]}`}>
                                        {STEP_LABEL[step.status] || step.status}
                                    </span>
                                </div>

                                {step.observation && <p className="text-xs text-warm-500 mt-1.5">{step.observation}</p>}
                                {step.error && <p className="text-xs text-red-600 mt-1.5">{step.error}</p>}

                                {step.verification?.checks?.length > 0 && (
                                    <ul className="mt-2 space-y-0.5">
                                        {step.verification.checks.map((check, i) => (
                                            <li key={i} className={`text-[11px] ${check.passed ? 'text-green-700' : 'text-red-600'}`}>
                                                {check.passed ? '✔' : '✖'} {check.criterion} — {check.detail}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ol>
                </div>

                {/* Linimasa keputusan */}
                <div className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                    <div className="p-4 border-b border-black/10 bg-warm-white">
                        <h3 className="font-bold text-sm text-[rgba(0,0,0,0.9)]">Jejak keputusan</h3>
                        <p className="text-xs text-warm-500 mt-0.5">Seluruh tindakan agent terekam berurutan.</p>
                    </div>
                    <ul className="p-4 space-y-3 max-h-[520px] overflow-y-auto">
                        {(task.events || []).map((event) => {
                            const style = EVENT_STYLE[event.type] || { dot: 'bg-warm-300', label: event.type };
                            return (
                                <li key={event.id} className="flex gap-2.5">
                                    <span className={`w-2 h-2 rounded-full mt-1.5 shrink-0 ${style.dot}`} />
                                    <div className="min-w-0">
                                        <p className="text-xs font-semibold text-[rgba(0,0,0,0.8)]">{style.label}</p>
                                        <p className="text-xs text-warm-500">{event.message}</p>
                                        <p className="text-[10px] text-warm-300 mt-0.5">{fmtTime(event.created_at)}</p>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            </div>

            {/* Hasil */}
            <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                <h3 className="font-bold text-sm text-[rgba(0,0,0,0.9)]">Hasil pekerjaan</h3>

                {task.final_output ? (
                    <pre className="mt-2 text-xs text-warm-600 whitespace-pre-wrap font-sans">{task.final_output}</pre>
                ) : (
                    <p className="text-xs text-warm-500 mt-2">Belum ada hasil akhir.</p>
                )}

                {task.deliverables?.length > 0 && (
                    <div className="flex flex-wrap gap-2 mt-3">
                        {task.deliverables.map((file) => (
                            <a key={file.index}
                                href={route('agent.tasks.download', { task: task.id, index: file.index })}
                                className="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg border border-black/10 text-notion-blue hover:bg-warm-50">
                                <ArrowDownTrayIcon className="w-3.5 h-3.5" /> {file.name}
                            </a>
                        ))}
                    </div>
                )}

                {task.verification?.checks?.length > 0 && (
                    <div className="mt-4 pt-3 border-t border-black/5">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-warm-500 mb-1.5">Pemeriksaan akhir</p>
                        <ul className="space-y-0.5">
                            {task.verification.checks.map((check, i) => (
                                <li key={i} className={`text-[11px] ${check.passed ? 'text-green-700' : 'text-red-600'}`}>
                                    {check.passed ? '✔' : '✖'} {check.criterion} — {check.detail}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            {/* Memori yang dipakai */}
            {(task.memory_used?.experiences?.length > 0 || task.memory_used?.lessons?.length > 0) && (
                <div className="bg-purple-50/60 rounded-xl border border-purple-100 p-4">
                    <h3 className="font-bold text-sm text-purple-900">Pengalaman yang dipakai pada pekerjaan ini</h3>

                    {task.memory_used.experiences.map((experience) => (
                        <p key={experience.id} className="text-xs text-purple-800 mt-2">
                            • <span className="font-medium">{experience.objective}</span> ({experience.outcome},
                            keyakinan {pct(experience.confidence)}){experience.reusable_strategy ? ` — ${experience.reusable_strategy}` : ''}
                        </p>
                    ))}

                    {task.memory_used.lessons.map((lesson) => (
                        <p key={lesson.id} className="text-xs text-purple-800 mt-2">
                            • Pelajaran: {lesson.lesson} <span className="text-purple-600">→ {lesson.recommendation}</span>
                        </p>
                    ))}
                </div>
            )}
        </div>
    );
}
