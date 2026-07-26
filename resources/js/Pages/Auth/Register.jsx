import { Head, useForm, Link } from '@inertiajs/react';

export default function Register({ divisions = [] }) {
    const form = useForm({
        name: '', email: '', password: '', password_confirmation: '',
        division_id: '', hierarchy: 'staff', employee_id: '', phone: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <>
            <Head title="Daftar" />
            <div className="min-h-screen flex items-center justify-center bg-slate-900 px-4 py-8">
                <div className="w-full max-w-md">
                    <div className="text-center mb-6">
                        <h1 className="text-white font-bold text-lg">Daftar Akun</h1>
                        <p className="text-slate-500 text-sm mt-1">Akun akan aktif setelah disetujui admin</p>
                    </div>

                    <div className="card">
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <label className="label">Nama</label>
                                <input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                                {form.errors.name && <p className="text-red-400 text-sm mt-1">{form.errors.name}</p>}
                            </div>
                            <div>
                                <label className="label">Email</label>
                                <input type="email" className="input" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                                {form.errors.email && <p className="text-red-400 text-sm mt-1">{form.errors.email}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="label">Password</label>
                                    <input type="password" className="input" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required />
                                </div>
                                <div>
                                    <label className="label">Konfirmasi</label>
                                    <input type="password" className="input" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} required />
                                </div>
                            </div>
                            {form.errors.password && <p className="text-red-400 text-sm">{form.errors.password}</p>}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="label">Divisi</label>
                                    <select className="input" value={form.data.division_id} onChange={(e) => form.setData('division_id', e.target.value)}>
                                        <option value="">— pilih —</option>
                                        {divisions.map((d) => (<option key={d.id} value={d.id}>{d.name}</option>))}
                                    </select>
                                </div>
                                <div>
                                    <label className="label">Jenjang</label>
                                    <select className="input" value={form.data.hierarchy} onChange={(e) => form.setData('hierarchy', e.target.value)}>
                                        <option value="staff">Staff</option>
                                        <option value="manager">Manager</option>
                                        <option value="director">Director</option>
                                        <option value="stakeholder">Stakeholder</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" disabled={form.processing} className="btn-primary w-full">
                                {form.processing ? 'Memproses...' : 'Daftar'}
                            </button>
                        </form>
                    </div>

                    <div className="mt-4 text-center text-sm">
                        <Link href={route('login')} className="text-blue-400 hover:text-blue-300">Sudah punya akun? Masuk</Link>
                    </div>
                </div>
            </div>
        </>
    );
}
