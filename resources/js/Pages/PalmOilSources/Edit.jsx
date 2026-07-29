import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

export default function PalmOilSourceEdit({ source }) {
    const form = useForm({
        name: source.name,
        supplier_name: source.supplier_name,
        latitude: source.latitude,
        longitude: source.longitude,
        province: source.province,
        city: source.city,
        district: source.district,
        address: source.address,
        stock_volume: source.stock_volume,
        unit: source.unit,
        stock_min: source.stock_min,
        price: source.price ?? '',
        notes: source.notes,
    });

    const priceInclPpn = form.data.price ? Math.round(Number(form.data.price) * 1.11) : 0;
    const rp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);

    const submit = (e) => {
        e.preventDefault();
        form.put(route('palm-oil-sources.update', source.id));
    };

    return (
        <>
            <Head title={`Edit ${source.name}`} />
            <AppLayout title={`Edit ${source.name}`}>
                <div className="mb-4">
                    <Link
                        href={route('palm-oil-sources.show', source.id)}
                        className="text-blue-400 hover:text-blue-300 flex items-center gap-2 text-sm"
                    >
                        <ArrowLeftIcon className="w-4 h-4" /> Kembali
                    </Link>
                </div>

                <div className="card max-w-2xl">
                    <form onSubmit={submit} className="space-y-6">
                        {/* Informasi Dasar */}
                        <div>
                            <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700">
                                Informasi Dasar
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <label className="label">
                                        Nama Sumber *
                                    </label>
                                    <input
                                        type="text"
                                        className="input"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        required
                                    />
                                    {form.errors.name && (
                                        <p className="text-red-400 text-sm mt-1">
                                            {form.errors.name}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="label">
                                        Nama Supplier
                                    </label>
                                    <input
                                        type="text"
                                        className="input"
                                        value={form.data.supplier_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'supplier_name',
                                                e.target.value
                                            )
                                        }
                                    />
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="label">
                                            Stok (Volume) *
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="input"
                                            value={form.data.stock_volume}
                                            onChange={(e) =>
                                                form.setData(
                                                    'stock_volume',
                                                    e.target.value
                                                )
                                            }
                                            required
                                        />
                                        {form.errors.stock_volume && (
                                            <p className="text-red-400 text-sm mt-1">
                                                {form.errors.stock_volume}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="label">
                                            Satuan *
                                        </label>
                                        <select
                                            className="input"
                                            value={form.data.unit}
                                            onChange={(e) =>
                                                form.setData(
                                                    'unit',
                                                    e.target.value
                                                )
                                            }
                                            required
                                        >
                                            <option value="ton">Ton</option>
                                            <option value="kg">Kilogram</option>
                                            <option value="g">Gram</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="label">
                                        Stok Minimum *
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        className="input"
                                        value={form.data.stock_min}
                                        onChange={(e) =>
                                            form.setData(
                                                'stock_min',
                                                e.target.value
                                            )
                                        }
                                        required
                                    />
                                    {form.errors.stock_min && (
                                        <p className="text-red-400 text-sm mt-1">
                                            {form.errors.stock_min}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="label">Harga per {form.data.unit} (Rp, sebelum PPN)</label>
                                    <input
                                        type="number"
                                        step="1"
                                        className="input"
                                        value={form.data.price}
                                        onChange={(e) => form.setData('price', e.target.value)}
                                        placeholder="1200000"
                                    />
                                    <p className="text-emerald-400/90 text-xs mt-1.5">
                                        Harga include PPN 11%: <span className="font-semibold tabular">{rp(priceInclPpn)}</span> / {form.data.unit}
                                    </p>
                                    {form.errors.price && <p className="text-red-400 text-sm mt-1">{form.errors.price}</p>}
                                </div>
                            </div>
                        </div>

                        {/* Lokasi & Koordinat */}
                        <div>
                            <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700">
                                Lokasi & Koordinat
                            </h3>
                            <div className="space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="label">
                                            Latitude *
                                        </label>
                                        <input
                                            type="number"
                                            step="0.00000001"
                                            className="input"
                                            value={form.data.latitude}
                                            onChange={(e) =>
                                                form.setData(
                                                    'latitude',
                                                    e.target.value
                                                )
                                            }
                                            required
                                        />
                                        {form.errors.latitude && (
                                            <p className="text-red-400 text-sm mt-1">
                                                {form.errors.latitude}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="label">
                                            Longitude *
                                        </label>
                                        <input
                                            type="number"
                                            step="0.00000001"
                                            className="input"
                                            value={form.data.longitude}
                                            onChange={(e) =>
                                                form.setData(
                                                    'longitude',
                                                    e.target.value
                                                )
                                            }
                                            required
                                        />
                                        {form.errors.longitude && (
                                            <p className="text-red-400 text-sm mt-1">
                                                {form.errors.longitude}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="label">
                                            Provinsi *
                                        </label>
                                        <input
                                            type="text"
                                            className="input"
                                            value={form.data.province}
                                            onChange={(e) =>
                                                form.setData(
                                                    'province',
                                                    e.target.value
                                                )
                                            }
                                            required
                                        />
                                        {form.errors.province && (
                                            <p className="text-red-400 text-sm mt-1">
                                                {form.errors.province}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="label">
                                            Kota/Kabupaten *
                                        </label>
                                        <input
                                            type="text"
                                            className="input"
                                            value={form.data.city}
                                            onChange={(e) =>
                                                form.setData('city', e.target.value)
                                            }
                                            required
                                        />
                                        {form.errors.city && (
                                            <p className="text-red-400 text-sm mt-1">
                                                {form.errors.city}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label className="label">Kecamatan</label>
                                    <input
                                        type="text"
                                        className="input"
                                        value={form.data.district}
                                        onChange={(e) =>
                                            form.setData(
                                                'district',
                                                e.target.value
                                            )
                                        }
                                    />
                                </div>

                                <div>
                                    <label className="label">
                                        Alamat Lengkap
                                    </label>
                                    <textarea
                                        className="input"
                                        rows="3"
                                        value={form.data.address}
                                        onChange={(e) =>
                                            form.setData('address', e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Catatan */}
                        <div>
                            <h3 className="text-white font-medium mb-4 pb-2 border-b border-slate-700">
                                Catatan Tambahan
                            </h3>
                            <div>
                                <label className="label">Catatan</label>
                                <textarea
                                    className="input"
                                    rows="3"
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                />
                            </div>
                        </div>

                        {/* Buttons */}
                        <div className="flex gap-3 pt-4 border-t border-slate-700">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="btn-primary"
                            >
                                {form.processing ? 'Menyimpan...' : 'Perbarui'}
                            </button>
                            <Link
                                href={route('palm-oil-sources.show', source.id)}
                                className="btn-secondary"
                            >
                                Batal
                            </Link>
                        </div>
                    </form>
                </div>
            </AppLayout>
        </>
    );
}
