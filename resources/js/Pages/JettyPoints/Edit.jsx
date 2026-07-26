import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import JettyPointForm from './Form';

export default function JettyPointEdit({ jetty }) {
    const form = useForm({
        name: jetty.name ?? '', operator: jetty.operator ?? '',
        latitude: jetty.latitude ?? '', longitude: jetty.longitude ?? '',
        province: jetty.province ?? '', city: jetty.city ?? '', district: jetty.district ?? '',
        address: jetty.address ?? '', capacity: jetty.capacity ?? '', unit: jetty.unit ?? 'ton',
        price: jetty.price ?? '', draft: jetty.draft ?? '',
        pic_name: jetty.pic_name ?? '', pic_phone: jetty.pic_phone ?? '', notes: jetty.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('jetty-points.update', jetty.id));
    };

    return (
        <>
            <Head title={`Edit ${jetty.name}`} />
            <AppLayout title={`Edit ${jetty.name}`}>
                <div className="mb-4">
                    <Link href={route('jetty-points.show', jetty.id)} className="text-blue-400 hover:text-blue-300 inline-flex items-center gap-2 text-sm">
                        <ArrowLeftIcon className="w-4 h-4" /> Kembali
                    </Link>
                </div>
                <div className="card max-w-2xl">
                    <form onSubmit={submit}>
                        <JettyPointForm form={form} />
                        <div className="flex gap-3 pt-6 mt-6 border-t border-slate-700/70">
                            <button type="submit" disabled={form.processing} className="btn-primary">
                                {form.processing ? 'Menyimpan...' : 'Perbarui'}
                            </button>
                            <Link href={route('jetty-points.show', jetty.id)} className="btn-secondary">Batal</Link>
                        </div>
                    </form>
                </div>
            </AppLayout>
        </>
    );
}
