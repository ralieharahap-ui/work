import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    ChatBubbleLeftRightIcon, PaperAirplaneIcon, BellAlertIcon,
    CheckCircleIcon, ExclamationTriangleIcon, MinusCircleIcon, ClockIcon,
} from '@heroicons/react/24/outline';

const STATUS_STYLE = {
    sent:    { cls: 'bg-green-50 text-green-700', icon: CheckCircleIcon, label: 'Terkirim' },
    failed:  { cls: 'bg-red-50 text-red-600', icon: ExclamationTriangleIcon, label: 'Gagal' },
    skipped: { cls: 'bg-warm-100 text-warm-500', icon: MinusCircleIcon, label: 'Dilewati' },
    pending: { cls: 'bg-amber-50 text-amber-700', icon: ClockIcon, label: 'Menunggu' },
};

const fmtTime = (d) =>
    d ? new Date(d).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-';

/**
 * Pusat kendali pengingat WhatsApp: status sambungan gateway, nomor pribadi
 * pengguna, kelengkapan nomor tim, dan riwayat pesan yang pernah dikirim.
 */
export default function RemindersView({ whatsapp, team, canRunReminders }) {
    const [number, setNumber] = useState(whatsapp.myNumber || '');
    const [optIn, setOptIn] = useState(whatsapp.myOptIn);
    const [busy, setBusy] = useState(false);

    const withoutNumber = team.filter((m) => m.is_active && !m.whatsapp_ready);

    const post = (routeName, data = {}) => {
        setBusy(true);
        router.post(route(routeName), data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const saveContact = (e) => {
        e.preventDefault();
        setBusy(true);
        router.patch(route('whatsapp.contact'), { whatsapp_number: number, whatsapp_opt_in: optIn }, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <div className="space-y-4">

            {/* Status gateway */}
            <div className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                <div className="p-4 border-b border-black/10 bg-warm-white flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="font-bold text-[rgba(0,0,0,0.9)] flex items-center gap-2">
                            <ChatBubbleLeftRightIcon className="w-5 h-5 text-notion-blue" /> Pengingat WhatsApp
                        </h3>
                        <p className="text-xs text-warm-500 mt-1">
                            Pengingat otomatis dikirim tiap hari pukul {whatsapp.reminderTime} kepada PIC yang tenggat
                            tugasnya tinggal {whatsapp.daysBefore.join(', ')} hari lagi — atau sudah terlewat.
                        </p>
                    </div>
                    {canRunReminders && (
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => post('whatsapp.run')}
                            className="flex items-center gap-1.5 bg-notion-blue hover:bg-notion-blue-active text-white text-sm font-medium px-3.5 py-2 rounded-md disabled:opacity-50 transition-colors"
                        >
                            <BellAlertIcon className="w-4 h-4" /> Kirim Pengingat Sekarang
                        </button>
                    )}
                </div>

                <div className="p-4 grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                    <Stat label="Status" value={whatsapp.ready ? 'Aktif' : (whatsapp.enabled ? 'Belum lengkap' : 'Nonaktif')}
                          tone={whatsapp.ready ? 'green' : (whatsapp.enabled ? 'amber' : 'grey')} />
                    <Stat label="Gateway" value={whatsapp.driver} tone="blue" />
                    <Stat label="Salinan ke grup" value={whatsapp.groupEnabled ? 'Aktif' : 'Nonaktif'}
                          tone={whatsapp.groupEnabled ? 'green' : 'grey'} />
                    <Stat label="Nomor tim belum diisi" value={String(withoutNumber.length)}
                          tone={withoutNumber.length > 0 ? 'amber' : 'green'} />
                </div>

                {!whatsapp.enabled && (
                    <p className="mx-4 mb-4 text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3">
                        Pengiriman WhatsApp masih dimatikan. Isi <code>WHATSAPP_ENABLED=true</code> beserta kredensial
                        gateway pada variabel lingkungan server, lalu deploy ulang. Selama nonaktif, seluruh pengingat
                        tetap dicatat di riwayat dengan status <em>dilewati</em>.
                    </p>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {/* Pengaturan nomor pribadi */}
                <form onSubmit={saveContact} className="bg-white rounded-xl border border-black/10 shadow-notion p-4 space-y-3">
                    <h4 className="font-bold text-sm text-[rgba(0,0,0,0.9)]">Nomor WhatsApp Saya</h4>
                    <div>
                        <input
                            type="tel"
                            value={number}
                            onChange={(e) => setNumber(e.target.value)}
                            placeholder="0812-3456-7890"
                            className="w-full p-2 border border-black/10 rounded-md text-sm text-[rgba(0,0,0,0.9)] outline-none focus:border-notion-blue"
                        />
                        <p className="text-[11px] text-warm-500 mt-1">
                            Boleh ditulis 0812… atau +62812… — sistem menormalkannya sendiri.
                        </p>
                    </div>
                    <label className="flex items-center gap-2 text-xs text-[rgba(0,0,0,0.75)]">
                        <input type="checkbox" checked={optIn} onChange={(e) => setOptIn(e.target.checked)} />
                        Saya bersedia menerima pengingat tugas lewat WhatsApp
                    </label>
                    <div className="flex gap-2">
                        <button type="submit" disabled={busy} className="flex-1 px-3 py-2 text-sm font-medium text-white bg-notion-blue rounded-md hover:bg-notion-blue-active disabled:opacity-50">
                            Simpan
                        </button>
                        <button
                            type="button"
                            disabled={busy || !whatsapp.myNumberReady}
                            title={whatsapp.myNumberReady ? 'Kirim pesan uji ke nomor Anda' : 'Simpan nomor terlebih dahulu'}
                            onClick={() => post('whatsapp.test')}
                            className="flex items-center gap-1.5 px-3 py-2 text-sm text-[rgba(0,0,0,0.8)] border border-black/10 rounded-md hover:bg-warm-white disabled:opacity-50"
                        >
                            <PaperAirplaneIcon className="w-4 h-4" /> Uji
                        </button>
                    </div>
                </form>

                {/* Kelengkapan nomor tim */}
                <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                    <h4 className="font-bold text-sm text-[rgba(0,0,0,0.9)] mb-2">Kelengkapan Nomor Tim</h4>
                    {withoutNumber.length === 0 ? (
                        <p className="text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg p-3">
                            Seluruh anggota tim aktif sudah punya nomor WhatsApp.
                        </p>
                    ) : (
                        <>
                            <p className="text-xs text-warm-500 mb-2">
                                {withoutNumber.length} anggota belum bisa dikirimi pengingat:
                            </p>
                            <ul className="space-y-1 max-h-40 overflow-y-auto">
                                {withoutNumber.map((m) => (
                                    <li key={m.id} className="text-xs text-[rgba(0,0,0,0.8)] flex items-center gap-2">
                                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" />
                                        {m.name} <span className="text-warm-300">{m.division || ''}</span>
                                    </li>
                                ))}
                            </ul>
                            <p className="text-[11px] text-warm-300 mt-2">
                                Super Admin dapat mengisinya lewat tab Tim → Edit pengguna.
                            </p>
                        </>
                    )}
                </div>

                {/* Anggota yang menolak notifikasi */}
                <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                    <h4 className="font-bold text-sm text-[rgba(0,0,0,0.9)] mb-2">Cara Kerja Mention</h4>
                    <ul className="text-xs text-warm-500 space-y-1.5 list-disc pl-4">
                        <li>Chat pribadi: pesan dibuka dengan <strong>@Nama PIC</strong> yang bersangkutan.</li>
                        <li>Grup tim (bila diaktifkan): nomor PIC ditandai sungguhan sehingga masuk ke notifikasi ponselnya.</li>
                        <li>Isi pesan merinci tugas terlambat, jatuh tempo hari ini, dan yang mendekati tenggat.</li>
                        <li>Satu digest per orang per hari; tombol pengingat per task tetap bisa dipakai kapan saja.</li>
                    </ul>
                </div>
            </div>

            {/* Riwayat */}
            <div className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                <div className="p-4 border-b border-black/10 bg-warm-white">
                    <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Riwayat Pengiriman</h3>
                    <p className="text-xs text-warm-500 mt-1">
                        50 pesan terakhir, termasuk yang gagal dan yang dilewati.
                        {!whatsapp.seesAllLog && ' Anda hanya melihat pesan yang ditujukan kepada Anda.'}
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[720px]">
                        <thead>
                            <tr className="bg-white border-b border-black/10 text-xs text-warm-500">
                                <th className="p-3 font-medium">Waktu</th>
                                <th className="p-3 font-medium">Penerima</th>
                                <th className="p-3 font-medium">Tujuan</th>
                                <th className="p-3 font-medium">Jenis</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-black/5 text-xs">
                            {whatsapp.log.length === 0 && (
                                <tr><td colSpan={6} className="p-6 text-center text-warm-500">Belum ada pengingat yang dikirim.</td></tr>
                            )}
                            {whatsapp.log.map((row) => {
                                const meta = STATUS_STYLE[row.status] || STATUS_STYLE.pending;
                                return (
                                    <tr key={row.id} className="hover:bg-warm-white transition-colors">
                                        <td className="p-3 text-warm-500 whitespace-nowrap">{fmtTime(row.sent_at || row.created_at)}</td>
                                        <td className="p-3 text-[rgba(0,0,0,0.85)] font-medium">{row.user?.name || '—'}</td>
                                        <td className="p-3 text-warm-500">{row.recipient || '—'}</td>
                                        <td className="p-3 text-warm-500">
                                            {row.channel === 'group' ? 'Grup' : 'Pribadi'} · {row.type === 'digest' ? 'harian' : row.type}
                                        </td>
                                        <td className="p-3">
                                            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold ${meta.cls}`}>
                                                <meta.icon className="w-3 h-3" /> {meta.label}
                                            </span>
                                        </td>
                                        <td className="p-3 text-warm-500 max-w-xs truncate" title={row.error || ''}>{row.error || '—'}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

function Stat({ label, value, tone }) {
    const tones = {
        green: 'bg-green-50 text-green-700 border-green-200',
        amber: 'bg-amber-50 text-amber-700 border-amber-200',
        blue: 'bg-notion-blue-badge-bg text-notion-blue-badge-text border-blue-200',
        grey: 'bg-warm-100 text-warm-500 border-black/10',
    };

    return (
        <div className={`rounded-lg border px-3 py-2 ${tones[tone] || tones.grey}`}>
            <p className="text-[10px] uppercase tracking-wider font-semibold opacity-80">{label}</p>
            <p className="text-sm font-bold mt-0.5 capitalize">{value}</p>
        </div>
    );
}
