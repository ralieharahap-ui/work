import { pct } from './constants';

/**
 * Isi memori agent: pengalaman (episodik), pelajaran, prosedur, dan statistik
 * performa tool. Ini yang membedakan asisten yang belajar dari yang sekadar
 * menjalankan perintah.
 */
export default function MemoryView({ memory }) {
    const panels = [
        {
            title: 'Pengalaman (memori episodik)',
            hint: 'Rekaman "apa yang terjadi" pada tiap pekerjaan yang pernah dikerjakan.',
            empty: 'Belum ada pengalaman — setiap pekerjaan yang selesai akan tercatat di sini.',
            rows: memory.experiences,
            render: (row) => (
                <>
                    <div className="flex items-start justify-between gap-2">
                        <p className="text-sm text-[rgba(0,0,0,0.9)]">{row.objective}</p>
                        <span className={`shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${
                            row.outcome === 'SUCCESS' ? 'bg-green-50 text-green-700'
                                : row.outcome === 'PARTIAL' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-600'}`}>
                            {row.outcome}
                        </span>
                    </div>
                    {row.strategy && <p className="text-xs text-warm-500 mt-1">{row.strategy}</p>}
                    <p className="text-[11px] text-warm-300 mt-1">
                        keyakinan {pct(row.confidence)} · keberhasilan {pct(row.success_rate)} · dipakai ulang {row.use_count}×
                    </p>
                </>
            ),
        },
        {
            title: 'Pelajaran',
            hint: 'Intisari kegagalan yang sudah diperbaiki, siap dipakai pada pekerjaan berikutnya.',
            empty: 'Belum ada pelajaran.',
            rows: memory.lessons,
            render: (row) => (
                <>
                    <p className="text-sm text-[rgba(0,0,0,0.9)]">{row.lesson}</p>
                    <p className="text-xs text-warm-500 mt-1">→ {row.recommendation}</p>
                    <p className="text-[11px] text-warm-300 mt-1">
                        berlaku saat: {row.trigger} · keyakinan {pct(row.confidence)} · dipakai {row.use_count}×
                        {!row.is_active && ' · tidak aktif'}
                    </p>
                </>
            ),
        },
        {
            title: 'Prosedur (memori prosedural)',
            hint: 'Kerangka langkah yang terbukti untuk satu keluarga pekerjaan.',
            empty: 'Belum ada prosedur yang terbentuk.',
            rows: memory.procedures,
            render: (row) => (
                <>
                    <p className="text-sm font-medium text-[rgba(0,0,0,0.9)]">{row.name}</p>
                    <p className="text-xs text-warm-500 mt-1">{row.steps.join(' → ')}</p>
                    <p className="text-[11px] text-warm-300 mt-1">
                        keberhasilan {pct(row.success_rate)} · dipakai {row.use_count}×
                    </p>
                </>
            ),
        },
    ];

    return (
        <div className="space-y-5">
            <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Memori Asisten</h3>
                <p className="text-xs text-warm-500 mt-1 max-w-3xl">
                    Setiap pekerjaan yang selesai diringkas menjadi pengalaman, pelajaran, dan prosedur — bukan
                    disimpan mentah-mentah sebagai percakapan. Data pribadi dan kredensial disaring sebelum masuk
                    ke sini.
                </p>
            </div>

            {panels.map((panel) => (
                <div key={panel.title} className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                    <div className="p-4 border-b border-black/10 bg-warm-white">
                        <h4 className="font-bold text-sm text-[rgba(0,0,0,0.9)]">{panel.title}</h4>
                        <p className="text-xs text-warm-500 mt-0.5">{panel.hint}</p>
                    </div>

                    {panel.rows?.length ? (
                        <ul className="divide-y divide-black/5">
                            {panel.rows.map((row) => <li key={row.id} className="p-3">{panel.render(row)}</li>)}
                        </ul>
                    ) : (
                        <p className="p-6 text-center text-xs text-warm-500">{panel.empty}</p>
                    )}
                </div>
            ))}

            <div className="bg-white rounded-xl border border-black/10 shadow-notion overflow-hidden">
                <div className="p-4 border-b border-black/10 bg-warm-white">
                    <h4 className="font-bold text-sm text-[rgba(0,0,0,0.9)]">Performa tool</h4>
                    <p className="text-xs text-warm-500 mt-0.5">Dipakai agent untuk memilih cara kerja yang paling sering berhasil.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[560px]">
                        <thead>
                            <tr className="bg-white border-b border-black/10 text-xs text-warm-500">
                                <th className="p-3 font-medium">Tool</th>
                                <th className="p-3 font-medium">Jenis pekerjaan</th>
                                <th className="p-3 font-medium text-right">Dipakai</th>
                                <th className="p-3 font-medium text-right">Keberhasilan</th>
                                <th className="p-3 font-medium text-right">Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-black/5 text-xs">
                            {memory.tool_stats?.length ? memory.tool_stats.map((stat, i) => (
                                <tr key={i} className="hover:bg-warm-white transition-colors">
                                    <td className="p-3 font-medium text-[rgba(0,0,0,0.9)]">{stat.tool}</td>
                                    <td className="p-3 text-warm-500">{stat.task_type}</td>
                                    <td className="p-3 text-right text-warm-500">{stat.runs}×</td>
                                    <td className={`p-3 text-right font-semibold ${stat.success_rate >= 0.8 ? 'text-green-700' : stat.success_rate >= 0.5 ? 'text-amber-700' : 'text-red-600'}`}>
                                        {pct(stat.success_rate)}
                                    </td>
                                    <td className="p-3 text-right text-warm-500">{stat.avg_ms} ms</td>
                                </tr>
                            )) : (
                                <tr><td colSpan={5} className="p-6 text-center text-warm-500">Belum ada statistik.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
