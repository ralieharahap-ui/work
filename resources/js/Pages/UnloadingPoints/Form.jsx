export default function UnloadingPointForm({ form }) {
    const set = (k) => (e) => form.setData(k, e.target.value);
    const Err = ({ k }) => form.errors[k] ? <p className="text-red-400 text-sm mt-1">{form.errors[k]}</p> : null;
    const priceInclPpn = form.data.price ? Math.round(Number(form.data.price) * 1.11) : 0;
    const rp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);

    return (
        <div className="space-y-6">
            {/* Informasi Titik Bongkar */}
            <div>
                <h3 className="section-title">Informasi Titik Bongkar</h3>
                <div className="space-y-4">
                    <div>
                        <label className="label">Nama Titik Bongkar *</label>
                        <input className="input" value={form.data.name} onChange={set('name')} placeholder="Contoh: Dermaga Bongkar Tidore" required />
                        <Err k="name" />
                    </div>
                    <div>
                        <label className="label">Nama Customer</label>
                        <input className="input" value={form.data.customer_name} onChange={set('customer_name')} placeholder="PT PLN (Persero)" />
                        <Err k="customer_name" />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Kapasitas Bongkar</label>
                            <input type="number" step="0.01" className="input" value={form.data.capacity} onChange={set('capacity')} placeholder="10000" />
                            <Err k="capacity" />
                        </div>
                        <div>
                            <label className="label">Satuan *</label>
                            <select className="input" value={form.data.unit} onChange={set('unit')} required>
                                <option value="ton">Ton</option>
                                <option value="kg">Kilogram</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="label">Harga yang dibayar customer per {form.data.unit} (Rp, sebelum PPN)</label>
                        <input type="number" step="1" className="input" value={form.data.price} onChange={set('price')} placeholder="1400000" />
                        <p className="text-emerald-400/90 text-xs mt-1.5">Harga include PPN 11%: <span className="font-semibold tabular">{rp(priceInclPpn)}</span> / {form.data.unit}</p>
                        <Err k="price" />
                    </div>
                    <div className="rounded-lg border border-slate-700 bg-slate-900/40 p-3">
                        <label className="flex items-center gap-2.5 text-sm text-slate-200 cursor-pointer select-none">
                            <input type="checkbox" checked={!!form.data.has_jetty}
                                   onChange={(e) => form.setData('has_jetty', e.target.checked)}
                                   className="rounded border-slate-600 bg-slate-800 text-blue-500 focus:ring-blue-500" />
                            ⚓ Lokasi ini memiliki dermaga
                        </label>
                        {form.data.has_jetty && (
                            <div className="mt-3">
                                <label className="label">Nama Dermaga</label>
                                <input className="input" value={form.data.jetty_name} onChange={set('jetty_name')} placeholder="Dermaga Soasio" />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Lokasi & Koordinat */}
            <div>
                <h3 className="section-title">Lokasi & Koordinat</h3>
                <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Latitude *</label>
                            <input type="number" step="0.00000001" className="input" value={form.data.latitude} onChange={set('latitude')} placeholder="0.6667" required />
                            <Err k="latitude" />
                        </div>
                        <div>
                            <label className="label">Longitude *</label>
                            <input type="number" step="0.00000001" className="input" value={form.data.longitude} onChange={set('longitude')} placeholder="127.3833" required />
                            <Err k="longitude" />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Provinsi *</label>
                            <input className="input" value={form.data.province} onChange={set('province')} placeholder="Maluku Utara" required />
                            <Err k="province" />
                        </div>
                        <div>
                            <label className="label">Kota/Kabupaten *</label>
                            <input className="input" value={form.data.city} onChange={set('city')} placeholder="Tidore" required />
                            <Err k="city" />
                        </div>
                    </div>
                    <div>
                        <label className="label">Kecamatan</label>
                        <input className="input" value={form.data.district} onChange={set('district')} placeholder="Tidore Tengah" />
                    </div>
                    <div>
                        <label className="label">Alamat Lengkap</label>
                        <textarea className="input" rows="2" value={form.data.address} onChange={set('address')} placeholder="Jl. Pelabuhan No. 1" />
                    </div>
                </div>
            </div>

            {/* Kontak PIC */}
            <div>
                <h3 className="section-title">Penanggung Jawab (PIC)</h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label className="label">Nama PIC</label>
                        <input className="input" value={form.data.pic_name} onChange={set('pic_name')} placeholder="Budi Santoso" />
                    </div>
                    <div>
                        <label className="label">No. Telepon PIC</label>
                        <input className="input" value={form.data.pic_phone} onChange={set('pic_phone')} placeholder="0812-3456-7890" />
                    </div>
                </div>
            </div>

            {/* Catatan */}
            <div>
                <h3 className="section-title">Catatan Tambahan</h3>
                <textarea className="input" rows="3" value={form.data.notes} onChange={set('notes')} placeholder="Contoh: jam operasional bongkar, draft dermaga..." />
            </div>
        </div>
    );
}
