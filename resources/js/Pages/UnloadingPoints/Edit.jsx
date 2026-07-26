import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import UnloadingPointForm from './Form';

export default function UnloadingPointEdit({ point }) {
    const form = useForm({
        name: point.name ?? '', customer_name: point.customer_name ?? '',
        latitude: point.latitude ?? '', longitude: point.longitude ?? '',
        province: point.province ?? '', city: point.city ?? '', district: point.district ?? '',
        address: point.address ?? '', capacity: point.capacity ?? '', unit: point.unit ?? 'ton',
        price: point.price ?? '', has_jetty: !!point.has_jetty, jetty_name: point.jetty_name ?? '',
        pic_name: point.pic_name ?? '', pic_phone: point.pic_phone ?? '', notes: point.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('unloading-points.update', point.id));
    };

    return (
        <>
            <Head title={`Edit ${point.name}`} />
            <AppLayout title={`Edit ${point.name}`}>
                <div className="mb-4">
                    <Link href={route('unloading-points.show', point.id)} className="text-blue-400 hover:text-blue-300 inline-flex items-center gap-2 text-sm">
                        <ArrowLeftIcon className="w-4 h-4" /> Kembali
                    </Link>
                </div>
                <div className="card max-w-2xl">
                    <form onSubmit={submit}>
                        <UnloadingPointForm form={form} />
                        <div className="flex gap-3 pt-6 mt-6 border-t border-slate-700/70">
                            <button type="submit" disabled={form.processing} className="btn-primary">
                                {form.processing ? 'Menyimpan...' : 'Perbarui'}
                            </button>
                            <Link href={route('unloading-points.show', point.id)} className="btn-secondary">Batal</Link>
                        </div>
                    </form>
                </div>
            </AppLayout>
        </>
    );
}
