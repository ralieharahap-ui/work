import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import MapViewer from '@/Components/MapViewer.jsx';
import { ArrowLeftIcon, MapPinIcon, UserIcon, PhoneIcon, DocumentTextIcon } from '@heroicons/react/24/outline';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(n);

export default function JettyPointShow({ jetty, can }) {
    return (
        <>
            <Head title={jetty.name} />
            <AppLayout title={jetty.name}>
                <div className="mb-4 flex justify-between items-center">
                    <Link href={route('jetty-points.index')} className="text-blue-400 hover:text-blue-300 inline-flex items-center gap-2 text-sm">
                        <ArrowLeftIcon className="w-4 h-4" /> Kembali ke Daftar
                    </Link>
                    {can?.edit && <Link href={route('jetty-points.edit', jetty.id)} className="btn-primary text-sm">Edit Dermaga</Link>}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                    <div className="card lg:col-span-2">
                        <h3 className="section-title"><span className="text-blue-400">🚢</span> Informasi Dermaga</h3>
                        <div className="grid sm:grid-cols-2 gap-4">
                            <div><p className="text-slate-400 text-xs">Nama Dermaga</p><p className="text-white font-medium">{jetty.name}</p></div>
                            <div><p className="text-slate-400 text-xs">Pengelola</p><p className="text-white">{jetty.operator || '—'}</p></div>
                            <div><p className="text-slate-400 text-xs">Kapasitas Sandar</p><p className="text-white font-medium tabular">{jetty.capacity ? `${fmt(jetty.capacity)} ${jetty.unit}` : '—'}</p></div>
                            <div><p className="text-slate-400 text-xs">Draft / Kedalaman</p><p className="text-white">{jetty.draft || '—'}</p></div>
                            <div>
                                <p className="text-slate-400 text-xs">Biaya Sandar incl. PPN 11%</p>
                                <p className="text-emerald-400 font-semibold tabular">{jetty.price > 0 ? `Rp ${fmt(Math.round(jetty.price_incl_ppn))} / ${jetty.unit}` : '—'}</p>
                                {jetty.price > 0 && <p className="text-slate-500 text-xs">Dasar: Rp {fmt(jetty.price)} (belum PPN)</p>}
                            </div>
                            <div>
                                <p className="text-slate-400 text-xs">Update Terakhir</p>
                                <p className="text-slate-300 text-sm">{new Date(jetty.updated_at).toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                        </div>
                    </div>

                    <div className="card">
                        <h3 className="section-title"><UserIcon className="w-[18px] h-[18px]" /> Penanggung Jawab</h3>
                        <div className="space-y-3">
                            <div><p className="text-slate-400 text-xs">Nama PIC</p><p className="text-white">{jetty.pic_name || '—'}</p></div>
                            <div><p className="text-slate-400 text-xs flex items-center gap-1"><PhoneIcon className="w-3.5 h-3.5" /> Telepon</p><p className="text-white">{jetty.pic_phone || '—'}</p></div>
                        </div>
                    </div>
                </div>

                <div className="card mb-6">
                    <h3 className="section-title"><MapPinIcon className="w-[18px] h-[18px] text-blue-400" /> Lokasi di Peta</h3>
                    <MapViewer jettyPoints={[jetty]} clickable={false} center={[parseFloat(jetty.latitude), parseFloat(jetty.longitude)]} zoom={9} />
                    <p className="text-slate-400 text-xs mt-3">🚢 Titik Dermaga</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div className="card">
                        <h3 className="section-title"><MapPinIcon className="w-[18px] h-[18px]" /> Lokasi</h3>
                        <div className="space-y-3">
                            <div><p className="text-slate-400 text-xs">Provinsi</p><p className="text-white">{jetty.province}</p></div>
                            <div><p className="text-slate-400 text-xs">Kota/Kabupaten</p><p className="text-white">{jetty.city}</p></div>
                            {jetty.district && <div><p className="text-slate-400 text-xs">Kecamatan</p><p className="text-white">{jetty.district}</p></div>}
                            {jetty.address && <div><p className="text-slate-400 text-xs">Alamat</p><p className="text-white text-sm">{jetty.address}</p></div>}
                        </div>
                    </div>
                    <div className="card">
                        <h3 className="section-title">Koordinat GPS</h3>
                        <div className="space-y-3">
                            <div className="bg-slate-900/50 rounded-lg p-3"><p className="text-slate-400 text-xs mb-1">Latitude</p><p className="text-white font-mono">{jetty.latitude}</p></div>
                            <div className="bg-slate-900/50 rounded-lg p-3"><p className="text-slate-400 text-xs mb-1">Longitude</p><p className="text-white font-mono">{jetty.longitude}</p></div>
                            <a href={`https://maps.google.com/?q=${jetty.latitude},${jetty.longitude}`} target="_blank" rel="noopener noreferrer" className="text-blue-400 hover:text-blue-300 text-sm inline-flex items-center gap-2 pt-1">Buka di Google Maps →</a>
                        </div>
                    </div>
                </div>

                {jetty.notes && (
                    <div className="card mt-4">
                        <h3 className="section-title"><DocumentTextIcon className="w-[18px] h-[18px]" /> Catatan</h3>
                        <p className="text-slate-300 text-sm whitespace-pre-wrap">{jetty.notes}</p>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
