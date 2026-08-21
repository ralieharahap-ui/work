import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.abs(Number(n) || 0));
const signed = (n) => `${Number(n) < 0 ? '(' : ''}${fmt(n)}${Number(n) < 0 ? ')' : ''}`;
const tgl = (s) => new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

export default function VendorLedger({ vendor, opening_balance, rows, current_balance, total_debit, total_credit }) {
    return (
        <>
            <Head title={'Riwayat Utang — ' + vendor.code} />
            <AppLayout title={'Riwayat Utang Vendor — ' + vendor.name}>
                <div className="flex flex-wrap items-center gap-3 mb-4 print:hidden">
                    <Link href={route('books.vendors.index')} className="btn-secondary">← Kembali</Link>
                    <div className="flex-1" />
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                <div className="card mb-4 grid sm:grid-cols-3 gap-4">
                    <div><p className="text-slate-400 text-xs">Vendor</p><p className="text-white font-medium">{vendor.code} — {vendor.name}</p></div>
                    <div><p className="text-slate-400 text-xs">Kontak</p><p className="text-slate-200">{vendor.phone || '—'}</p></div>
                    <div><p className="text-slate-400 text-xs">Saldo Utang Berjalan</p><p className="text-amber-300 font-bold text-lg">{signed(current_balance)}</p></div>
                </div>

                <div className="card overflow-x-auto">
                    <p className="text-slate-400 text-sm mb-3">Otomatis dari jurnal yang telah dirilis (Posted) dengan kode bantu = <b>{vendor.code}</b> pada akun utang.</p>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                <th className="table-header">Tanggal</th>
                                <th className="table-header">No. Jurnal</th>
                                <th className="table-header">Keterangan</th>
                                <th className="table-header">Akun</th>
                                <th className="table-header text-right">Debet</th>
                                <th className="table-header text-right">Kredit</th>
                                <th className="table-header text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="border-b border-slate-700/50 bg-slate-900/40">
                                <td className="table-cell" colSpan={4}>Saldo Awal</td>
                                <td className="table-cell text-right">—</td>
                                <td className="table-cell text-right">—</td>
                                <td className="table-cell text-right font-medium">{signed(opening_balance)}</td>
                            </tr>
                            {rows.length === 0 && (
                                <tr><td colSpan={7} className="table-cell text-center text-slate-400">Belum ada mutasi dari jurnal.</td></tr>
                            )}
                            {rows.map((r, i) => (
                                <tr key={i} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell whitespace-nowrap">{tgl(r.entry_date)}</td>
                                    <td className="table-cell font-mono text-xs">{r.entry_no}</td>
                                    <td className="table-cell">{r.description}</td>
                                    <td className="table-cell text-slate-400 text-xs">{r.account}</td>
                                    <td className="table-cell text-right">{r.debit ? fmt(r.debit) : '—'}</td>
                                    <td className="table-cell text-right">{r.credit ? fmt(r.credit) : '—'}</td>
                                    <td className="table-cell text-right">{signed(r.balance)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-slate-600">
                                <td colSpan={4} className="table-cell font-medium">Total Mutasi / Saldo Akhir</td>
                                <td className="table-cell text-right font-bold">{fmt(total_debit)}</td>
                                <td className="table-cell text-right font-bold">{fmt(total_credit)}</td>
                                <td className="table-cell text-right font-bold text-amber-300">{signed(current_balance)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
