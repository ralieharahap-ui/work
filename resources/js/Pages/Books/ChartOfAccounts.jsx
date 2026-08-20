import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const fmt = (n) => (Number(n) ? new Intl.NumberFormat('id-ID').format(n) : '—');

// Kelompokkan akun berdasarkan Kelompok FS, urutan grup mengikuti kemunculan
// pertama (akun sudah terurut kode dari server, jadi 1xxx→6xxx).
function buildGroups(accounts) {
    const map = new Map();
    accounts.forEach((a) => {
        const key = a.fs_group || 'Lainnya';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(a);
    });
    return Array.from(map, ([name, items]) => ({ name, items }));
}

// Satu blok Kelompok FS: baris judul grup + baris akun + subtotal.
function FsGroup({ groupName, items, colSpan, fmt, can_manage, openEdit }) {
    const subDebit = items.reduce((s, a) => s + Number(a.debit || 0), 0);
    const subCredit = items.reduce((s, a) => s + Number(a.credit || 0), 0);
    return (
        <>
            <tr className="bg-slate-800/60">
                <td colSpan={colSpan} className="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-blue-300">
                    {groupName}
                </td>
            </tr>
            {items.map((acc) => (
                <tr key={acc.code} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                    <td className="table-cell font-mono text-xs">{acc.code}</td>
                    <td className="table-cell">{acc.name}</td>
                    <td className="table-cell text-slate-400">{acc.account_type ?? acc.type}</td>
                    <td className="table-cell text-center">
                        {acc.normal_balance && (
                            <span className={`badge ${acc.normal_balance === 'Db' ? 'badge-blue' : 'badge-amber'}`}>{acc.normal_balance}</span>
                        )}
                    </td>
                    <td className="table-cell text-right">{fmt(acc.debit)}</td>
                    <td className="table-cell text-right">{fmt(acc.credit)}</td>
                    {can_manage && (
                        <td className="table-cell whitespace-nowrap print:hidden">
                            <button onClick={() => openEdit(acc)} className="text-blue-400 hover:text-blue-300 text-xs mr-3">Edit</button>
                            <button onClick={() => router.delete(route('books.accounts.destroy', acc.id), { preserveScroll: true })}
                                className="text-red-400 hover:text-red-300 text-xs">Hapus</button>
                        </td>
                    )}
                </tr>
            ))}
            <tr className="border-b border-slate-600/60 text-slate-400">
                <td colSpan={4} className="px-3 py-1 text-xs italic text-right">Subtotal {groupName}</td>
                <td className="px-3 py-1 text-xs text-right">{fmt(subDebit)}</td>
                <td className="px-3 py-1 text-xs text-right">{fmt(subCredit)}</td>
                {can_manage && <td className="print:hidden"></td>}
            </tr>
        </>
    );
}

export default function ChartOfAccounts({ accounts, total_debit, total_credit, control_accounts, can_manage, type_options }) {
    const [tab, setTab] = useState('coa');
    const [editing, setEditing] = useState(null);
    const groups = buildGroups(accounts);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        code: '', name: '', type: 'asset', account_type: '', fs_group: '', normal_balance: '', report: '', is_active: true,
    });

    const openNew = () => {
        reset();
        setData({ code: '', name: '', type: 'asset', account_type: '', fs_group: '', normal_balance: 'Db', report: 'NRC', is_active: true });
        setEditing({});
    };
    const openEdit = (a) => {
        setData({
            code: a.code, name: a.name, type: a.type, account_type: a.account_type || '',
            fs_group: a.fs_group || '', normal_balance: a.normal_balance || '', report: a.report || '', is_active: true,
        });
        setEditing(a);
    };
    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { reset(); setEditing(null); } };
        if (editing && editing.id) put(route('books.accounts.update', editing.id), opts);
        else post(route('books.accounts.store'), opts);
    };

    return (
        <>
            <Head title="Daftar Akun (COA)" />
            <AppLayout title="Daftar Akun & Control Account">
                <div className="flex flex-wrap items-center gap-2 mb-4 print:hidden">
                    <button onClick={() => setTab('coa')} className={tab === 'coa' ? 'btn-primary' : 'btn-secondary'}>Daftar Akun</button>
                    <button onClick={() => setTab('control')} className={tab === 'control' ? 'btn-primary' : 'btn-secondary'}>Control Account</button>
                    <div className="flex-1" />
                    {can_manage && tab === 'coa' && <button onClick={openNew} className="btn-primary">+ Akun</button>}
                    <button onClick={() => window.print()} className="btn-secondary">🖨️ Cetak / PDF</button>
                </div>

                {can_manage && editing && tab === 'coa' && (
                    <form onSubmit={submit} className="card mb-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-3 print:hidden">
                        <div>
                            <label className="label">Kode Akun</label>
                            <input className="input font-mono" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="1101" required />
                            {errors.code && <p className="text-red-400 text-xs mt-1">{errors.code}</p>}
                        </div>
                        <div className="lg:col-span-2">
                            <label className="label">Nama Akun</label>
                            <input className="input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        </div>
                        <div>
                            <label className="label">Type (kelompok)</label>
                            <select className="input capitalize" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                {type_options.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="label">TYPE Akun (control)</label>
                            <input className="input" value={data.account_type} onChange={(e) => setData('account_type', e.target.value)} placeholder="mis. Kas di Bank" />
                        </div>
                        <div>
                            <label className="label">Kelompok FS</label>
                            <input className="input" value={data.fs_group} onChange={(e) => setData('fs_group', e.target.value)} placeholder="mis. Aset Lancar" />
                        </div>
                        <div>
                            <label className="label">Posisi Normal</label>
                            <select className="input" value={data.normal_balance} onChange={(e) => setData('normal_balance', e.target.value)}>
                                <option value="">—</option>
                                <option value="Db">Db (Debet)</option>
                                <option value="Kr">Kr (Kredit)</option>
                            </select>
                        </div>
                        <div>
                            <label className="label">Laporan</label>
                            <select className="input" value={data.report} onChange={(e) => setData('report', e.target.value)}>
                                <option value="">—</option>
                                <option value="NRC">NRC (Neraca)</option>
                                <option value="LR">LR (Laba/Rugi)</option>
                            </select>
                        </div>
                        <div className="sm:col-span-2 lg:col-span-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setEditing(null)} className="btn-secondary">Batal</button>
                            <button type="submit" className="btn-primary" disabled={processing}>{editing.id ? 'Simpan Perubahan' : 'Simpan Akun'}</button>
                        </div>
                    </form>
                )}

                {tab === 'coa' && (
                    <div className="card overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-slate-700">
                                    <th className="table-header">KODE Akun</th>
                                    <th className="table-header">NAMA AKUN</th>
                                    <th className="table-header">TYPE Akun</th>
                                    <th className="table-header text-center">Db/Kr</th>
                                    <th className="table-header text-right">DEBET</th>
                                    <th className="table-header text-right">KREDIT</th>
                                    {can_manage && <th className="table-header print:hidden"></th>}
                                </tr>
                            </thead>
                            <tbody>
                                {groups.map(({ name: groupName, items }) => (
                                    <FsGroup key={groupName || 'lainnya'} groupName={groupName} items={items}
                                             colSpan={can_manage ? 7 : 6} fmt={fmt} can_manage={can_manage}
                                             openEdit={openEdit} />
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t border-slate-600">
                                    <td colSpan={4} className="table-cell font-medium">Total</td>
                                    <td className="table-cell text-right font-bold text-blue-300">{fmt(total_debit)}</td>
                                    <td className="table-cell text-right font-bold text-blue-300">{fmt(total_credit)}</td>
                                    {can_manage && <td className="print:hidden"></td>}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}

                {tab === 'control' && (
                    <div className="card overflow-x-auto">
                        <p className="text-slate-400 text-sm mb-3">
                            Referensi TYPE AKUN (Control Account) beserta posisi normal (Db/Kr) dan pemetaan laporan
                            (NRC = Neraca, LR = Laba/Rugi).
                        </p>
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-slate-700">
                                    <th className="table-header">Kelompok</th>
                                    <th className="table-header">TYPE AKUN</th>
                                    <th className="table-header text-center">Posisi Normal</th>
                                    <th className="table-header text-center">Laporan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {control_accounts.map(([group, typeName, pos, report], i) => (
                                    <tr key={i} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                                        <td className="table-cell text-slate-400">{group}</td>
                                        <td className="table-cell">{typeName}</td>
                                        <td className="table-cell text-center">
                                            <span className={`badge ${pos === 'Db' ? 'badge-blue' : 'badge-amber'}`}>{pos}</span>
                                        </td>
                                        <td className="table-cell text-center">
                                            <span className={`badge ${report === 'NRC' ? 'badge-green' : 'badge-purple'}`}>{report}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
