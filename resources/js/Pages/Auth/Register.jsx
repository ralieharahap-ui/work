import { Head, useForm, Link } from '@inertiajs/react';
import { InformationCircleIcon } from '@heroicons/react/24/outline';

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
            <Head title="Daftar Akun" />
            <div className="app-viewport flex items-start sm:items-center justify-center bg-slate-900 px-4 py-8 overflow-y-auto">
                <div className="w-full max-w-md">
                    <div className="text-center mb-6">
                        <div className="w-11 h-11 mx-auto rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow-sm mb-3">
                            <span className="text-white font-bold text-sm">GEP</span>
                        </div>
                        <h1 className="text-white font-bold text-lg">Daftar Akun Baru</h1>
                        <p className="text-slate-500 text-sm mt-1">PT Geosys Energi Prima — ERP System</p>
                    </div>

                    <div className="card">
                        <div className="flex items-start gap-2.5 bg-blue-500/10 border border-blue-500/20 rounded-lg p-3 mb-5 text-xs text-blue-300">
                            <InformationCircleIcon className="w-4 h-4 shrink-0 mt-0.5" />
                            <span>Akun baru perlu disetujui administrator sebelum bisa digunakan untuk masuk.</span>
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="label">Nama Lengkap *</label>
                                <input
                                    className="input"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Nama sesuai identitas"
                                    autoFocus
                                    required
                                />
                                {form.errors.name && <p className="text-red-400 text-sm mt-1">{form.errors.name}</p>}
                            </div>

                            <div>
                                <label className="label">Email *</label>
                                <input
                                    type="email"
                                    className="input"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    placeholder="nama@pt-gep.com"
                                    required
                                />
                                {form.errors.email && <p className="text-red-400 text-sm mt-1">{form.errors.email}</p>}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="label">Password *</label>
                                    <input
                                        type="password"
                                        className="input"
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                        placeholder="••••••••"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="label">Konfirmasi *</label>
                                    <input
                                        type="password"
                                        className="input"
                                        value={form.data.password_confirmation}
                                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                        placeholder="••••••••"
                                        required
                                    />
                                </div>
                            </div>
                            {form.errors.password ? (
                                <p className="text-red-400 text-sm -mt-2">{form.errors.password}</p>
                            ) : (
                                <p className="text-slate-500 text-xs -mt-2">Minimal 8 karakter, mengandung huruf besar dan angka.</p>
                            )}

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="label">Divisi *</label>
                                    <select
                                        className="input"
                                        value={form.data.division_id}
                                        onChange={(e) => form.setData('division_id', e.target.value)}
                                        required
                                    >
                                        <option value="">— pilih —</option>
                                        {divisions.map((d) => (
                                            <option key={d.id} value={d.id}>{d.name}</option>
                                        ))}
                                    </select>
                                    {form.errors.division_id && <p className="text-red-400 text-sm mt-1">{form.errors.division_id}</p>}
                                </div>
                                <div>
                                    <label className="label">Jenjang *</label>
                                    <select
                                        className="input"
                                        value={form.data.hierarchy}
                                        onChange={(e) => form.setData('hierarchy', e.target.value)}
                                        required
                                    >
                                        <option value="staff">Staff</option>
                                        <option value="manager">Manager</option>
                                        <option value="director">Director</option>
                                        <option value="stakeholder">Stakeholder</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="label">NIK / ID Pegawai</label>
                                    <input
                                        className="input"
                                        value={form.data.employee_id}
                                        onChange={(e) => form.setData('employee_id', e.target.value)}
                                        placeholder="Opsional"
                                    />
                                    {form.errors.employee_id && <p className="text-red-400 text-sm mt-1">{form.errors.employee_id}</p>}
                                </div>
                                <div>
                                    <label className="label">No. Telepon</label>
                                    <input
                                        className="input"
                                        value={form.data.phone}
                                        onChange={(e) => form.setData('phone', e.target.value)}
                                        placeholder="Opsional"
                                    />
                                </div>
                            </div>

                            <button type="submit" disabled={form.processing} className="btn-primary w-full">
                                {form.processing ? 'Memproses...' : 'Daftar'}
                            </button>
                        </form>
                    </div>

                    <div className="mt-4 text-center text-sm">
                        <span className="text-slate-500">Sudah punya akun? </span>
                        <Link href={route('login')} className="text-blue-400 hover:text-blue-300 font-medium">
                            Masuk
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
