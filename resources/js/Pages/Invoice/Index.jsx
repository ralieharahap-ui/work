import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PlusIcon, CheckCircleIcon, BanknotesIcon } from '@heroicons/react/24/outline';
import { useState } from 'react';

const fmt = (n) => new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', maximumFractionDigits:0 }).format(n);

const statusColors = {
    draft:     'bg-slate-700 text-slate-300',
    sent:      'bg-blue-900 text-blue-300',
    paid:      'bg-green-900 text-green-300',
    overdue:   'bg-red-900 text-red-300',
    cancelled: 'bg-slate-700 text-slate-400',
};

export default function InvoiceIndex({ invoices, can }) {
    const [payInv, setPayInv] = useState(null);
    const pay = useForm({ amount: '' });

    const submitPay = (e) => {
        e.preventDefault();
        pay.post(route('invoices.payment', payInv.id), { onSuccess: () => { pay.reset(); setPayInv(null); } });
    };

    return (
        <>
            <Head title="Invoice" />
            <AppLayout title="Invoice">
                <div className="flex justify-between items-center mb-4">
                    <div className="flex gap-2">
                        {['','draft','sent','paid','overdue'].map((s) => (
                            <Link key={s} href={`/invoices${s ? '?status='+s : ''}`}
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
                        <Link href={route('invoices.create')} className="btn-primary flex items-center gap-2">
                            <PlusIcon className="w-4 h-4" /> Buat Invoice
                        </Link>
                    )}
                </div>

                <div className="card overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-slate-700">
                                {['No. Invoice','Customer','Mekanisme','Total','Hak Terima','Status','Jatuh Tempo','Aksi'].map(h => (
                                    <th key={h} className="table-header">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.data.map((inv) => (
                                <tr key={inv.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                    <td className="table-cell font-mono text-blue-400 text-xs">{inv.invoice_no}</td>
                                    <td className="table-cell">{inv.customer?.name}</td>
                                    <td className="table-cell">
                                        {inv.tax_mechanism === 'wapu'
                                            ? <span className="text-xs bg-orange-900 text-orange-300 px-2 py-0.5 rounded-full">WAPU</span>
                                            : <span className="text-xs bg-slate-700 text-slate-300 px-2 py-0.5 rounded-full">Regular</span>
                                        }
                                    </td>
                                    <td className="table-cell">{fmt(inv.total)}</td>
                                    <td className="table-cell text-slate-400">{fmt(inv.cash_expected)}</td>
                                    <td className="table-cell">
                                        <span className={`text-xs px-2 py-0.5 rounded-full ${statusColors[inv.status]}`}>{inv.status}</span>
                                    </td>
                                    <td className={`table-cell text-xs ${inv.status === 'overdue' ? 'text-red-400' : 'text-slate-400'}`}>{inv.due_date}</td>
                                    <td className="table-cell">
                                        <div className="flex gap-2">
                                            {can?.finalize && inv.status === 'draft' && (
                                                <Link href={route('invoices.finalize', inv.id)} method="post" as="button"
                                                    className="text-blue-400 hover:text-blue-300 text-xs flex items-center gap-1">
                                                    <CheckCircleIcon className="w-3 h-3" /> Kirim
                                                </Link>
                                            )}
                                            {can?.approve && ['sent','overdue'].includes(inv.status) && (
                                                <button onClick={() => { setPayInv(inv); pay.setData('amount', inv.cash_expected); }}
                                                    className="text-green-400 hover:text-green-300 text-xs flex items-center gap-1">
                                                    <BanknotesIcon className="w-3 h-3" /> Bayar
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Modal Pembayaran */}
                {payInv && (
                    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
                        <div className="bg-slate-800 rounded-xl p-6 w-full max-w-sm border border-slate-700">
                            <h2 className="text-white font-medium mb-1">Catat Pembayaran</h2>
                            <p className="text-slate-400 text-sm mb-1">{payInv.invoice_no} — {payInv.customer?.name}</p>
                            {payInv.tax_mechanism === 'wapu' && (
                                <div className="bg-orange-900/30 border border-orange-800 rounded-lg px-3 py-2 text-xs text-orange-300 mb-3">
                                    Invoice WAPU — kas diterima = DPP saja ({fmt(payInv.cash_expected)}). PPN ({fmt(payInv.ppn_dipungut_wapu)}) disetor langsung oleh PLN.
                                </div>
                            )}
                            <form onSubmit={submitPay} className="space-y-3">
                                <div>
                                    <label className="label">Jumlah Kas Diterima (Rp)</label>
                                    <input className="input" type="number" value={pay.data.amount} onChange={e=>pay.setData('amount',e.target.value)}/>
                                </div>
                                <div className="flex gap-3 pt-2">
                                    <button type="submit" disabled={pay.processing} className="btn-primary">Catat</button>
                                    <button type="button" onClick={() => setPayInv(null)} className="btn-secondary">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
