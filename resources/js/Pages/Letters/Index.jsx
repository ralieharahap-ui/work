import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PlusIcon, CheckIcon, XMarkIcon } from '@heroicons/react/24/outline';

const statusColors = {
    draft:    'bg-slate-700 text-slate-300',
    review:   'bg-yellow-900 text-yellow-300',
    approved: 'bg-green-900 text-green-300',
    sent:     'bg-blue-900 text-blue-300',
    archived: 'bg-slate-800 text-slate-400',
};

export default function LettersIndex({ letters, can }) {
    return (
        <>
            <Head title="Surat Menyurat" />
            <AppLayout title="Surat Menyurat">
                <div className="flex justify-between items-center mb-4">
                    <div className="flex gap-2">
                        {['','draft','review','approved','sent'].map((s) => (
                            <Link key={s} href={`/letters${s ? '?status='+s : ''}`}
                                className={`text-xs px-3 py-1.5 rounded-lg border transition
                                    ${(new URLSearchParams(window.location.search).get('status') ?? '') === s
                                        ? 'bg-blue-600 border-blue-600 text-white'
                                        : 'border-slate-700 text-slate-400 hover:text-white'}`}
                            >
                                {s || 'Semua'}
                            </Link>
                        ))}
                    </div>
                    {can?.create && (
                        <Link href={route('letters.create')} className="btn-primary flex items-center gap-2">
                            <PlusIcon className="w-4 h-4" /> Buat Surat
                        </Link>
                    )}
                </div>

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                {['No. Surat','Perihal','Jenis','Arah','Divisi','Status','Dibuat','Aksi'].map(h => (
                                    <th key={h} className="table-header">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {letters.data.map((letter) => (
                                <tr key={letter.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-xs text-blue-400">{letter.letter_no}</td>
                                    <td className="table-cell max-w-xs truncate">{letter.subject}</td>
                                    <td className="table-cell text-slate-400 capitalize">{letter.type}</td>
                                    <td className="table-cell">
                                        <span className={`text-xs px-2 py-0.5 rounded-full ${letter.direction === 'external' ? 'bg-purple-900 text-purple-300' : 'bg-slate-700 text-slate-300'}`}>
                                            {letter.direction}
                                        </span>
                                    </td>
                                    <td className="table-cell text-slate-400 text-xs">{letter.division?.name}</td>
                                    <td className="table-cell">
                                        <span className={`text-xs px-2 py-0.5 rounded-full ${statusColors[letter.status]}`}>{letter.status}</span>
                                    </td>
                                    <td className="table-cell text-slate-400 text-xs">{letter.created_at?.slice(0,10)}</td>
                                    <td className="table-cell">
                                        <div className="flex gap-2">
                                            <Link href={route('letters.show', letter.id)} className="text-blue-400 hover:text-blue-300 text-xs">Lihat</Link>
                                            {can?.approve && letter.status === 'review' && (
                                                <>
                                                    <Link href={route('letters.approve', letter.id)} method="post" as="button"
                                                        className="text-green-400 hover:text-green-300 text-xs flex items-center gap-0.5">
                                                        <CheckIcon className="w-3 h-3"/> Setujui
                                                    </Link>
                                                    <Link href={route('letters.reject', letter.id)} method="post" as="button"
                                                        className="text-red-400 hover:text-red-300 text-xs flex items-center gap-0.5">
                                                        <XMarkIcon className="w-3 h-3"/> Tolak
                                                    </Link>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
