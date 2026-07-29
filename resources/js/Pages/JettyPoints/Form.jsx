export default function JettyPointForm({ form }) {
    const set = (k) => (e) => form.setData(k, e.target.value);
    const Err = ({ k }) => form.errors[k] ? <p className="text-red-400 text-sm mt-1">{form.errors[k]}</p> : null;
    const priceInclPpn = form.data.price ? Math.round(Number(form.data.price) * 1.11) : 0;
    const rp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);

    return (
        <div className="space-y-6">
            <div>
                <h3 className="section-title">🚢 Informasi Dermaga</h3>
                <div className="space-y-4">
                    <div>
                        <label className="label">Nama Dermaga *</label>
                        <input className="input" value={form.data.name} onChange={set('name')} placeholder="Contoh: Dermaga Soasio Tidore" required />
                        <Err k="name" />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Pengelola / Operator</label>
                            <input className="input" value={form.data.operator} onChange={set('operator')} placeholder="PT Pelindo" />
                        </div>
                        <div>
                            <label className="label">Draft / Kedalaman</label>
                            <input className="input" value={form.data.draft} onChange={set('draft')} placeholder="-6 m LWS" />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Kapasitas Sandar Tongkang</label>
                            <input type="number" step="0.01" className="input" value={form.data.capacity} onChange={set('capacity')} placeholder="5000" />
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
                        <label className="label">Biaya Sandar per {form.data.unit} (Rp, sebelum PPN)</label>
                        <input type="number" step="1" className="input" value={form.data.price} onChange={set('price')} placeholder="50000" />
                        <p className="text-emerald-400/90 text-xs mt-1.5">Include PPN 11%: <span className="font-semibold tabular">{rp(priceInclPpn)}</span> / {form.data.unit}</p>
                        <Err k="price" />
                    </div>
                </div>
            </div>

            <div>
                <h3 className="section-title">Lokasi & Koordinat</h3>
                <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Latitude *</label>
                            <input type="number" step="0.00000001" className="input" value={form.data.latitude} onChange={set('latitude')} placeholder="0.6900" required />
                            <Err k="latitude" />
                        </div>
                        <div>
                            <label className="label">Longitude *</label>
                            <input type="number" step="0.00000001" className="input" value={form.data.longitude} onChange={set('longitude')} placeholder="127.4000" required />
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
                        <input className="input" value={form.data.district} onChange={set('district')} placeholder="Tidore Utara" />
                    </div>
                    <div>
                        <label className="label">Alamat Lengkap</label>
                        <textarea className="input" rows="2" value={form.data.address} onChange={set('address')} placeholder="Pelabuhan Soasio" />
                    </div>
                </div>
            </div>

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

            <div>
                <h3 className="section-title">Catatan Tambahan</h3>
                <textarea className="input" rows="3" value={form.data.notes} onChange={set('notes')} placeholder="Contoh: jam operasional, fasilitas conveyor..." />
            </div>
        </div>
    );
}
