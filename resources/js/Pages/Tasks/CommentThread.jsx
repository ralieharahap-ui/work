import { useState } from 'react';
import { router } from '@inertiajs/react';
import { PaperAirplaneIcon, ChatBubbleLeftEllipsisIcon } from '@heroicons/react/24/outline';

export default function CommentThread({ task }) {
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const comments = task.comments || [];

    const submit = (e) => {
        e.preventDefault();
        if (!body.trim()) return;
        setSending(true);
        router.post(route('tasks.comments.store', task.id), { body }, {
            preserveScroll: true,
            onSuccess: () => setBody(''),
            onFinish: () => setSending(false),
        });
    };

    return (
        <div className="bg-warm-white p-4 rounded-lg border border-black/10">
            <label className="block text-sm font-medium text-[rgba(0,0,0,0.8)] mb-2 flex items-center gap-2">
                <ChatBubbleLeftEllipsisIcon className="w-4 h-4" /> Diskusi / Interaksi Tim
            </label>

            <div className="space-y-2 mb-3 max-h-52 overflow-y-auto pr-1">
                {comments.length === 0 && (
                    <p className="text-xs text-warm-300 italic">Belum ada komentar. Mulai diskusi dengan rekan tim.</p>
                )}
                {comments.map((c) => (
                    <div key={c.id} className="bg-white p-2.5 rounded border border-black/10">
                        <div className="flex items-center justify-between mb-1">
                            <span className="text-xs font-semibold text-[rgba(0,0,0,0.85)]">{c.user?.name || 'Pengguna'}</span>
                            <span className="text-[10px] text-warm-300">{new Date(c.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}</span>
                        </div>
                        <p className="text-sm text-[rgba(0,0,0,0.8)] whitespace-pre-wrap break-words">{c.body}</p>
                    </div>
                ))}
            </div>

            <form onSubmit={submit} className="flex gap-2">
                <input
                    type="text"
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    placeholder="Tulis komentar untuk tim..."
                    className="flex-1 p-2 text-sm border border-black/10 rounded-md text-[rgba(0,0,0,0.9)] outline-none focus:border-notion-blue focus:ring-1 focus:ring-notion-blue"
                />
                <button
                    type="submit"
                    disabled={sending || !body.trim()}
                    className="px-3 py-2 bg-black/5 text-[rgba(0,0,0,0.85)] rounded-md text-sm hover:bg-black/10 transition-colors disabled:opacity-40"
                >
                    <PaperAirplaneIcon className="w-4 h-4" />
                </button>
            </form>
        </div>
    );
}
