import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import MapViewer from '@/Components/MapViewer.jsx';
import { ArrowLeftIcon, MapPinIcon, UserIcon, PhoneIcon, DocumentTextIcon } from '@heroicons/react/24/outline';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(n);

export default function UnloadingPointShow({ point, can }) {
    return (
        <>
            <Head title={point.name} />
            <AppLayout title={point.name}>
                <div className="mb-4 flex justify-between items-center">
                    <Link href={route('unloading-points.index')} className="text-blue-400 hover:text-blue-300 inline-flex items-center gap-2 text-sm">
                        <ArrowLeftIcon className="w-4 h-4" /> Kembali ke Daftar
                    </Link>
                    {can?.edit && (
                        <Link href={route('unloading-points.edit', point.id)} className="btn-primary text-sm">Edit Titik Bongkar</Link>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                    <div className="card lg:col-span-2">
                        <h3 className="section-title">
                            <span className="w-2.5 h-2.5 rounded-full bg-red-500" /> Informasi Titik Bongkar
                        </h3>
                        <div className="grid sm:grid-cols-2 gap-4">
                            <div>
                                <p className="text-slate-400 text-xs">Nama Titik</p>
                                <p className="text-white font-medium">{point.name}</p>
                            </div>
                            <div>
                                <p className="text-slate-400 text-xs">Customer</p>
                                <p className="text-white">{point.customer_name || '—'}</p>
                            </div>
                            <div>
                                <p className="text-slate-400 text-xs">Status</p>
                                <span className={`badge ${point.status === 'active' ? 'badge-green' : 'badge-slate'} mt-0.5`}>
                                    {point.status === 'active' ? 'Aktif' : 'Nonaktif'}
                                </span>
                            </div>
                            <div>
                                <p className="text-slate-400 text-xs">Kapasitas Bongkar</p>
                                <p className="text-white font-medium tabular">{point.capacity ? `${fmt(point.capacity)} ${point.unit}` : '—'}</p>
                            </div>
                            <div>
                                <p className="text-slate-400 text-xs">Harga incl. PPN 11%</p>
                                <p className="text-emerald-400 font-semibold tabular">{point.price > 0 ? `Rp ${fmt(Math.round(point.price_incl_ppn))} / ${point.unit}` : '—'}</p>
                                {point.price > 0 && <p className="text-slate-500 text-xs">Dasar: Rp {fmt(point.price)} (belum PPN)</p>}
                            </div>
                            <div>
                                <p className="text-slate-400 text-xs">Dermaga</p>
                                {point.has_jetty
                                    ? <span className="badge badge-blue mt-0.5">⚓ {point.jetty_name || 'Ada dermaga'}</span>
                                    : <p className="text-slate-300">Tidak ada</p>}
                            </div>
                            <div>
                                <p className="text-slate-400 text-xs">Update Terakhir</p>
                                <p className="text-slate-300 text-sm">{new Date(point.updated_at).toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                        </div>
                    </div>

                    <div className="card">
                        <h3 className="section-title"><UserIcon className="w-[18px] h-[18px]" /> Penanggung Jawab</h3>
                        <div className="space-y-3">
                            <div>
                                <p className="text-slate-400 text-xs">Nama PIC</p>
                                <p className="text-white">{point.pic_name || '—'}</p>
                            </div>
                            <div>
                                <p className="text-slate-400 text-xs flex items-center gap-1"><PhoneIcon className="w-3.5 h-3.5" /> Telepon</p>
                                <p className="text-white">{point.pic_phone || '—'}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="card mb-6">
                    <h3 className="section-title"><MapPinIcon className="w-[18px] h-[18px] text-red-400" /> Lokasi di Peta</h3>
                    <MapViewer unloadingPoints={[point]} center={[parseFloat(point.latitude), parseFloat(point.longitude)]} zoom={9} />
                    <p className="text-slate-400 text-xs mt-3">🔴 Titik Bongkar (Customer)</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div className="card">
                        <h3 className="section-title"><MapPinIcon className="w-[18px] h-[18px]" /> Lokasi</h3>
                        <div className="space-y-3">
                            <div><p className="text-slate-400 text-xs">Provinsi</p><p className="text-white">{point.province}</p></div>
                            <div><p className="text-slate-400 text-xs">Kota/Kabupaten</p><p className="text-white">{point.city}</p></div>
                            {point.district && <div><p className="text-slate-400 text-xs">Kecamatan</p><p className="text-white">{point.district}</p></div>}
                            {point.address && <div><p className="text-slate-400 text-xs">Alamat</p><p className="text-white text-sm">{point.address}</p></div>}
                        </div>
                    </div>

                    <div className="card">
                        <h3 className="section-title">Koordinat GPS</h3>
                        <div className="space-y-3">
                            <div className="bg-slate-900/50 rounded-lg p-3"><p className="text-slate-400 text-xs mb-1">Latitude</p><p className="text-white font-mono">{point.latitude}</p></div>
                            <div className="bg-slate-900/50 rounded-lg p-3"><p className="text-slate-400 text-xs mb-1">Longitude</p><p className="text-white font-mono">{point.longitude}</p></div>
                            <a href={`https://maps.google.com/?q=${point.latitude},${point.longitude}`} target="_blank" rel="noopener noreferrer"
                               className="text-blue-400 hover:text-blue-300 text-sm inline-flex items-center gap-2 pt-1">Buka di Google Maps →</a>
                        </div>
                    </div>
                </div>

                {point.notes && (
                    <div className="card mt-4">
                        <h3 className="section-title"><DocumentTextIcon className="w-[18px] h-[18px]" /> Catatan</h3>
                        <p className="text-slate-300 text-sm whitespace-pre-wrap">{point.notes}</p>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
