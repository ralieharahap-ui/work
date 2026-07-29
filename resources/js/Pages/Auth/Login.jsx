import { Head, useForm, Link } from '@inertiajs/react';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('login'), { onFinish: () => form.reset('password') });
    };

    return (
        <>
            <Head title="Masuk" />
            <div className="app-viewport flex items-center justify-center bg-slate-900 px-4 py-8 overflow-y-auto">
                <div className="w-full max-w-sm">
                    <div className="text-center mb-6">
                        <h1 className="text-white font-bold text-lg">PT Geosys Energi Prima</h1>
                        <p className="text-slate-500 text-sm mt-1">ERP System — Masuk ke akun Anda</p>
                    </div>

                    <div className="card">
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="label">Email</label>
                                <input
                                    type="email"
                                    className="input"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    placeholder="admin@pt-gep.com"
                                    autoFocus
                                    required
                                />
                                {form.errors.email && (
                                    <p className="text-red-400 text-sm mt-1">{form.errors.email}</p>
                                )}
                            </div>

                            <div>
                                <label className="label">Password</label>
                                <input
                                    type="password"
                                    className="input"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                    placeholder="••••••••"
                                    required
                                />
                                {form.errors.password && (
                                    <p className="text-red-400 text-sm mt-1">{form.errors.password}</p>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-slate-400 select-none">
                                <input
                                    type="checkbox"
                                    checked={form.data.remember}
                                    onChange={(e) => form.setData('remember', e.target.checked)}
                                    className="rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500"
                                />
                                Ingat saya
                            </label>

                            <button
                                type="submit"
                                disabled={form.processing}
                                className="btn-primary w-full"
                            >
                                {form.processing ? 'Memproses...' : 'Masuk'}
                            </button>
                        </form>
                    </div>

                    <div className="mt-4 text-center text-sm">
                        <span className="text-slate-500">Belum punya akun? </span>
                        <Link href={route('register')} className="text-blue-400 hover:text-blue-300 font-medium">
                            Daftar sekarang
                        </Link>
                    </div>

                    {import.meta.env.DEV && (
                        <div className="mt-4 text-center text-xs text-slate-500">
                            <p>Akun demo administrator (hanya tampil saat development):</p>
                            <p className="text-slate-400 font-mono mt-1">admin@pt-gep.com / Admin@12345</p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
