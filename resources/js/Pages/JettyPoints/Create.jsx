import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import JettyPointForm from './Form';

export default function JettyPointCreate() {
    const form = useForm({
        name: '', operator: '', latitude: '', longitude: '',
        province: '', city: '', district: '', address: '',
        capacity: '', unit: 'ton', price: '', draft: '',
        pic_name: '', pic_phone: '', notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('jetty-points.store'));
    };

    return (
        <>
            <Head title="Tambah Titik Dermaga" />
            <AppLayout title="Tambah Titik Dermaga">
                <div className="mb-4">
                    <Link href={route('jetty-points.index')} className="text-blue-400 hover:text-blue-300 inline-flex items-center gap-2 text-sm">
                        <ArrowLeftIcon className="w-4 h-4" /> Kembali
                    </Link>
                </div>
                <div className="card max-w-2xl">
                    <form onSubmit={submit}>
                        <JettyPointForm form={form} />
                        <div className="flex gap-3 pt-6 mt-6 border-t border-slate-700/70">
                            <button type="submit" disabled={form.processing} className="btn-primary">
                                {form.processing ? 'Menyimpan...' : 'Simpan'}
                            </button>
                            <Link href={route('jetty-points.index')} className="btn-secondary">Batal</Link>
                        </div>
                    </form>
                </div>
            </AppLayout>
        </>
    );
}
