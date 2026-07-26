import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import UnloadingPointForm from './Form';

export default function UnloadingPointCreate() {
    const form = useForm({
        name: '', customer_name: '', latitude: '', longitude: '',
        province: '', city: '', district: '', address: '',
        capacity: '', unit: 'ton', price: '', has_jetty: false, jetty_name: '',
        pic_name: '', pic_phone: '', notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('unloading-points.store'));
    };

    return (
        <>
            <Head title="Tambah Titik Bongkar" />
            <AppLayout title="Tambah Titik Bongkar (Customer)">
                <div className="mb-4">
                    <Link href={route('unloading-points.index')} className="text-blue-400 hover:text-blue-300 inline-flex items-center gap-2 text-sm">
                        <ArrowLeftIcon className="w-4 h-4" /> Kembali
                    </Link>
                </div>
                <div className="card max-w-2xl">
                    <form onSubmit={submit}>
                        <UnloadingPointForm form={form} />
                        <div className="flex gap-3 pt-6 mt-6 border-t border-slate-700/70">
                            <button type="submit" disabled={form.processing} className="btn-primary">
                                {form.processing ? 'Menyimpan...' : 'Simpan'}
                            </button>
                            <Link href={route('unloading-points.index')} className="btn-secondary">Batal</Link>
                        </div>
                    </form>
                </div>
            </AppLayout>
        </>
    );
}
