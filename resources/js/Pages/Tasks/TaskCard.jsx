import {
    CheckCircleIcon, ClockIcon, ChatBubbleLeftEllipsisIcon,
    ChatBubbleLeftRightIcon, DocumentTextIcon, CheckBadgeIcon,
} from '@heroicons/react/24/outline';
import { PRIORITY_BADGE, KANBAN_EDITABLE_STATUSES, fmtDateShort } from './constants';

export default function TaskCard({ task, onEdit, onStatusChange, onCloseRequest, onRemind, onOpenEvidence, canEdit }) {
    const isOverdue = task.is_overdue;
    const totalSubtasks = task.checklist_items?.length || 0;
    const completedSubtasks = task.checklist_items?.filter((s) => s.is_completed)?.length || 0;
    const commentCount = task.comments?.length || 0;
    const documents = task.evidence_documents ?? [];
    const signedDocs = documents.filter((d) => d.is_signed).length;

    return (
        <div className="bg-white p-3.5 rounded-xl shadow-notion border border-black/10 hover:shadow-notion-deep transition-shadow group flex flex-col">
            <div className="flex justify-between items-start mb-2">
                <span className={`text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${PRIORITY_BADGE[task.priority]}`}>
                    {task.priority}
                </span>
                {isOverdue && (
                    <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-red-50 text-red-600">
                        Kadaluarsa
                    </span>
                )}
            </div>

            <h4
                className="font-bold text-[rgba(0,0,0,0.95)] text-sm leading-snug mb-1.5 cursor-pointer hover:text-notion-blue"
                onClick={onEdit}
            >
                {task.title}
            </h4>

            {task.project?.title && (
                <p className="text-xs text-warm-500 mb-2 truncate">📂 {task.project.title}</p>
            )}

            <div className="flex items-center gap-3 text-xs text-warm-500 mb-2">
                {totalSubtasks > 0 && (
                    <span className="flex items-center gap-1">
                        <CheckCircleIcon className={`w-3.5 h-3.5 ${completedSubtasks === totalSubtasks ? 'text-green-600' : ''}`} />
                        {completedSubtasks}/{totalSubtasks}
                    </span>
                )}
                {commentCount > 0 && (
                    <span className="flex items-center gap-1">
                        <ChatBubbleLeftEllipsisIcon className="w-3.5 h-3.5" /> {commentCount}
                    </span>
                )}
                {documents.length > 0 && (
                    <span
                        className={`flex items-center gap-1 ${signedDocs > 0 ? 'text-green-700' : ''}`}
                        title={`${documents.length} dokumen bukti, ${signedDocs} sudah ditandatangani`}
                    >
                        {signedDocs > 0 ? <CheckBadgeIcon className="w-3.5 h-3.5" /> : <DocumentTextIcon className="w-3.5 h-3.5" />}
                        {signedDocs > 0 ? `${signedDocs}/${documents.length}` : documents.length}
                    </span>
                )}
            </div>

            {/* Baris aksi dibiarkan membungkus: kolom Kanban sempit dan kini ada
                tiga tombol aksi di samping pemilih status. */}
            <div className="mt-auto pt-3 flex flex-wrap items-center justify-between gap-y-2 border-t border-black/5">
                <div className="flex items-center gap-1.5">
                    <div
                        className="w-6 h-6 rounded-full bg-notion-blue-badge-bg text-notion-blue-badge-text text-[11px] flex items-center justify-center font-semibold"
                        title={task.pic?.name || 'Belum ditugaskan'}
                    >
                        {task.pic ? task.pic.name.charAt(0).toUpperCase() : '?'}
                    </div>
                    {task.deadline && (
                        <div className={`flex items-center gap-1 text-[11px] ${isOverdue ? 'text-red-600 font-semibold bg-red-50 px-1 py-0.5 rounded' : 'text-warm-500'}`}>
                            <ClockIcon className="w-3 h-3" />
                            {fmtDateShort(task.deadline)}
                        </div>
                    )}
                </div>

                {canEdit && (
                    <div className="flex items-center gap-0.5 ml-auto">
                        {task.status !== 'Done' && (
                            <>
                                <button
                                    type="button"
                                    title={task.pic ? `Ingatkan ${task.pic.name} lewat WhatsApp` : 'Task ini belum punya PIC'}
                                    disabled={!task.pic}
                                    onClick={onRemind}
                                    className="text-emerald-600 hover:bg-emerald-50 rounded p-1 transition-colors disabled:opacity-30 disabled:hover:bg-transparent"
                                >
                                    <ChatBubbleLeftRightIcon className="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    title="Dokumen bukti (evidence)"
                                    onClick={onOpenEvidence}
                                    className="text-notion-blue hover:bg-notion-blue-badge-bg rounded p-1 transition-colors"
                                >
                                    <DocumentTextIcon className="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    title="Tandai Selesai (lampirkan bukti)"
                                    onClick={onCloseRequest}
                                    className="text-green-600 hover:bg-green-50 rounded p-1 transition-colors"
                                >
                                    <CheckCircleIcon className="w-4 h-4" />
                                </button>
                            </>
                        )}
                        <select
                            value={task.status}
                            onChange={(e) => onStatusChange(e.target.value)}
                            onClick={(e) => e.stopPropagation()}
                            className="text-[11px] border border-transparent bg-warm-white hover:border-black/10 rounded px-1 py-0.5 ml-0.5 max-w-[86px] text-warm-500 outline-none cursor-pointer"
                        >
                            {KANBAN_EDITABLE_STATUSES.map((s) => (
                                <option key={s} value={s}>{s}</option>
                            ))}
                            {task.status === 'Done' && <option value="Done">Done</option>}
                        </select>
                    </div>
                )}
            </div>
        </div>
    );
}
