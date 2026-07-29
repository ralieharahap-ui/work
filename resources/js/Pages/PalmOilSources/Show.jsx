import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import MapViewer from '@/Components/MapViewer.jsx';
import { ArrowLeftIcon, MapPinIcon, DocumentTextIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(n);

export default function PalmOilSourceShow({ source, can }) {
    const isLowStock = parseFloat(source.stock_volume) <= parseFloat(source.stock_min);

    return (
        <>
            <Head title={source.name} />
            <AppLayout title={source.name}>
                <div className="mb-4 flex justify-between items-center">
                    <Link
                        href={route('palm-oil-sources.index')}
                        className="text-blue-400 hover:text-blue-300 flex items-center gap-2 text-sm"
                    >
                        <ArrowLeftIcon className="w-4 h-4" /> Kembali ke Daftar
                    </Link>
                    {can?.edit && (
                        <Link
                            href={route('palm-oil-sources.edit', source.id)}
                            className="btn-primary text-sm"
                        >
                            Edit Sumber
                        </Link>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                    {/* Kartu Informasi Dasar */}
                    <div className="card lg:col-span-2">
                        <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700">
                            Informasi Dasar
                        </h3>
                        <div className="space-y-3">
                            <div>
                                <p className="text-slate-400 text-sm">Nama Sumber</p>
                                <p className="text-white font-medium">{source.name}</p>
                            </div>
                            {source.supplier_name && (
                                <div>
                                    <p className="text-slate-400 text-sm">Supplier</p>
                                    <p className="text-white">{source.supplier_name}</p>
                                </div>
                            )}
                            <div>
                                <p className="text-slate-400 text-sm">Status</p>
                                <span
                                    className={`text-sm px-3 py-1 rounded-full inline-block ${
                                        source.status === 'active'
                                            ? 'bg-green-900/40 text-green-400'
                                            : 'bg-slate-700/40 text-slate-400'
                                    }`}
                                >
                                    {source.status === 'active'
                                        ? 'Aktif'
                                        : 'Tidak Aktif'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Kartu Stok */}
                    <div className={`card ${isLowStock ? 'border-l-2 border-l-red-500' : 'border-l-2 border-l-green-500'}`}>
                        <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700">
                            Status Stok
                        </h3>
                        <div className="space-y-4">
                            <div>
                                <p className="text-slate-400 text-sm mb-1">Stok Saat Ini</p>
                                <p
                                    className={`text-2xl font-bold ${
                                        isLowStock ? 'text-red-400' : 'text-green-400'
                                    }`}
                                >
                                    {fmt(source.stock_volume)}
                                </p>
                                <p className="text-slate-400 text-xs">{source.unit}</p>
                            </div>
                            <div>
                                <p className="text-slate-400 text-sm mb-1">Stok Minimum</p>
                                <p className="text-white font-medium">{fmt(source.stock_min)} {source.unit}</p>
                            </div>
                            <div className="border-t border-slate-700/70 pt-3">
                                <p className="text-slate-400 text-sm mb-1">Harga incl. PPN 11%</p>
                                <p className="text-emerald-400 font-semibold tabular">
                                    {source.price > 0 ? `Rp ${fmt(Math.round(source.price_incl_ppn))} / ${source.unit}` : '—'}
                                </p>
                                {source.price > 0 && <p className="text-slate-500 text-xs mt-0.5">Dasar: Rp {fmt(source.price)} (belum PPN)</p>}
                            </div>
                            <div>
                                <p className="text-slate-400 text-sm mb-1">Update Terakhir</p>
                                <p className="text-slate-300 text-sm">{new Date(source.updated_at).toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            {isLowStock && (
                                <div className="bg-red-900/20 border border-red-800 rounded p-2 flex items-start gap-2">
                                    <ExclamationTriangleIcon className="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" />
                                    <span className="text-red-400 text-xs">
                                        Stok di bawah minimum. Perlu pengisian.
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Peta */}
                <div className="card mb-6">
                    <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700">
                        Visualisasi Peta
                    </h3>
                    <MapViewer
                        sources={[source]}
                        clickable={false}
                        center={[parseFloat(source.latitude), parseFloat(source.longitude)]}
                        zoom={9}
                    />
                    <p className="text-slate-400 text-xs mt-3">🌴 Lokasi sumber cangkang sawit</p>
                </div>

                {/* Informasi Lokasi */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div className="card">
                        <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700 flex items-center gap-2">
                            <MapPinIcon className="w-5 h-5" /> Lokasi
                        </h3>
                        <div className="space-y-3">
                            <div>
                                <p className="text-slate-400 text-sm">Provinsi</p>
                                <p className="text-white">{source.province}</p>
                            </div>
                            <div>
                                <p className="text-slate-400 text-sm">Kota/Kabupaten</p>
                                <p className="text-white">{source.city}</p>
                            </div>
                            {source.district && (
                                <div>
                                    <p className="text-slate-400 text-sm">Kecamatan</p>
                                    <p className="text-white">{source.district}</p>
                                </div>
                            )}
                            {source.address && (
                                <div>
                                    <p className="text-slate-400 text-sm">Alamat Lengkap</p>
                                    <p className="text-white text-sm">{source.address}</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Koordinat */}
                    <div className="card">
                        <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700">
                            Koordinat GPS
                        </h3>
                        <div className="space-y-3">
                            <div className="bg-slate-700/30 rounded p-3">
                                <p className="text-slate-400 text-xs mb-1">Latitude</p>
                                <p className="text-white font-mono">{source.latitude}</p>
                            </div>
                            <div className="bg-slate-700/30 rounded p-3">
                                <p className="text-slate-400 text-xs mb-1">Longitude</p>
                                <p className="text-white font-mono">{source.longitude}</p>
                            </div>
                            <a
                                href={`https://maps.google.com/?q=${source.latitude},${source.longitude}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-blue-400 hover:text-blue-300 text-sm flex items-center gap-2 pt-2"
                            >
                                Buka di Google Maps →
                            </a>
                        </div>
                    </div>
                </div>

                {/* Catatan */}
                {source.notes && (
                    <div className="card mt-4">
                        <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700 flex items-center gap-2">
                            <DocumentTextIcon className="w-5 h-5" /> Catatan
                        </h3>
                        <p className="text-slate-300 text-sm whitespace-pre-wrap">
                            {source.notes}
                        </p>
                    </div>
                )}

                {/* Metadata */}
                <div className="mt-6 text-slate-500 text-xs flex justify-between">
                    <span>
                        Dibuat: {new Date(source.created_at).toLocaleDateString('id-ID')}
                    </span>
                    <span>
                        Diperbarui: {new Date(source.updated_at).toLocaleDateString('id-ID')}
                    </span>
                </div>
            </AppLayout>
        </>
    );
}
