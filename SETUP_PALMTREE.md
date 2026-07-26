# 🌴 Modul Sumber Cangkang Sawit - Setup & Dokumentasi

Modul ini memungkinkan Anda untuk mengelola data sumber cangkang sawit (biomassa) dengan visualisasi peta interaktif.

## ✨ Fitur

✅ **Input Data Sumber Cangkang**
- Nama sumber
- Nama supplier
- Volume/stok
- Lokasi (latitude/longitude)
- Provinsi, kota, kecamatan
- Catatan tambahan

✅ **Manajemen Stok**
- Tracking volume stok saat ini
- Pengaturan stok minimum
- Alert otomatis jika stok dibawah minimum

✅ **Visualisasi Peta Interaktif**
- Peta real-time dengan Leaflet.js
- Penanda sumber cangkang (hijau)
- Penanda PLTU (merah)
- Info popup saat diklik
- Zoom & pan otomatis sesuai lokasi

✅ **CRUD Operations**
- Tambah sumber baru
- Edit informasi sumber
- Lihat detail & visualisasi peta
- Hapus sumber

## 📋 Struktur File

```
project/
├── app/
│   ├── Http/Controllers/
│   │   └── PalmOilSourceController.php          ← API Controller
│   └── Models/
│       └── PalmOilSource.php                    ← Model
│
├── database/
│   └── migrations/
│       └── 2026_07_24_000001_create_palm_oil_sources_table.php
│
├── resources/
│   └── js/
│       ├── Components/
│       │   └── MapViewer.jsx                    ← Komponen Peta
│       └── Pages/
│           └── PalmOilSources/
│               ├── Index.jsx                    ← Daftar sumber
│               ├── Create.jsx                   ← Form tambah
│               ├── Edit.jsx                     ← Form edit
│               └── Show.jsx                     ← Detail & peta
│
└── routes/
    └── web.php                                  ← Routes sudah ditambahkan
```

## 🚀 Instalasi & Setup

### 1. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### 2. Setup Database

```bash
# Jalankan migration
php artisan migrate

# (Optional) Seed data default
php artisan db:seed
```

### 3. Konfigurasi Environment

Pastikan `.env` sudah dikonfigurasi dengan benar:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gep_erp
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Run Development Server

**Terminal 1 - Laravel Backend:**
```bash
php artisan serve
```

**Terminal 2 - Frontend (Vite):**
```bash
npm run dev
```

Aplikasi akan berjalan di `http://localhost:8000`

### 5. Build untuk Production

```bash
npm run build
```

## 📖 API Endpoints

### List Sumber Cangkang
```
GET /palm-oil-sources
```

### Buat Sumber Baru
```
POST /palm-oil-sources
Content-Type: application/json

{
  "name": "Sumber PKS Riau",
  "supplier_name": "PT Biomassa",
  "latitude": "-0.789275",
  "longitude": "113.921327",
  "province": "Riau",
  "city": "Palalawan",
  "district": "Sungai Apit",
  "address": "Jl. Merdeka No. 123",
  "stock_volume": "1000",
  "unit": "ton",
  "stock_min": "100",
  "notes": "Sumber utama"
}
```

### Detail Sumber
```
GET /palm-oil-sources/{id}
```

### Update Sumber
```
PUT /palm-oil-sources/{id}
Content-Type: application/json

{...updated fields...}
```

### Hapus Sumber
```
DELETE /palm-oil-sources/{id}
```

### Get Data untuk API (JSON)
```
GET /palm-oil-sources/api/data
```

Response:
```json
{
  "sources": [...],
  "totalVolume": 5000,
  "sourceCount": 5,
  "lowStockCount": 2
}
```

## 🗺️ Komponen MapViewer

Komponen React untuk menampilkan peta dengan markers:

```jsx
import MapViewer from '@/Components/MapViewer';

<MapViewer
  sources={sources}          // Array data sumber cangkang
  pltuLocations={pltuLocations}  // Array data PLTU
  center={[lat, lng]}        // Pusat peta (optional)
  zoom={8}                   // Level zoom (optional)
/>
```

### Properties

- **sources** (Array): Data sumber cangkang
  - `id`, `name`, `latitude`, `longitude`, `city`, `province`
  - `stock_volume`, `unit`, `stock_min`, `supplier_name`

- **pltuLocations** (Array): Data PLTU
  - `id`, `name`, `latitude`, `longitude`, `city`, `province`, `capacity`

- **center** (Array): [latitude, longitude] - Default: [-0.789275, 113.921327]

- **zoom** (Number): Level zoom - Default: 5

## 🔒 Permissions

Fitur ini menggunakan permission-based access control:

- `inventory.view` - Melihat daftar sumber
- `inventory.create` - Menambah sumber baru
- `inventory.edit` - Mengedit sumber
- `inventory.delete` - Menghapus sumber

Konfigurasi permission ada di seeders yang sudah ada.

## 📊 Database Schema

```sql
CREATE TABLE palm_oil_sources (
  id CHAR(36) PRIMARY KEY,
  organization_id CHAR(36),
  name VARCHAR(255) NOT NULL,
  supplier_name VARCHAR(255),
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  province VARCHAR(100) NOT NULL,
  city VARCHAR(100) NOT NULL,
  district VARCHAR(100),
  address TEXT,
  stock_volume DECIMAL(10, 2) NOT NULL,
  unit VARCHAR(50) DEFAULT 'ton',
  stock_min DECIMAL(10, 2) DEFAULT 0,
  status VARCHAR(50) DEFAULT 'active',
  notes TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE INDEX idx_org_status ON palm_oil_sources(organization_id, status);
```

## 🎨 UI Components Used

- **Inertia + React**: Server-side rendering dengan React
- **Tailwind CSS**: Styling
- **Heroicons**: Icon library
- **Leaflet.js**: Interactive map
- **OpenStreetMap**: Map tiles (free & open-source)

## 🐛 Troubleshooting

### Peta tidak muncul
- Pastikan Leaflet sudah terinstall: `npm install leaflet`
- Check browser console untuk error messages
- Pastikan CDN OpenStreetMap accessible

### Marker tidak muncul
- Verifikasi data latitude/longitude valid
- Pastikan format: latitude = -90 to 90, longitude = -180 to 180

### Permissions denied
- Check user role dan permission assignment
- Pastikan seeder sudah dijalankan: `php artisan db:seed`

### Database error
- Jalankan migration: `php artisan migrate`
- Reset database: `php artisan migrate:fresh`

## 📝 Contoh Data Koordinat

Beberapa koordinat PLTU dan sumber untuk testing:

```javascript
// Sumber Cangkang
{
  name: "PKS Riau",
  latitude: -0.789275,
  longitude: 113.921327,
  city: "Palalawan",
  province: "Riau"
}

// PLTU
{
  name: "PLTU Sofifi & Tidore",
  latitude: 0.6667,
  longitude: 127.3833,
  city: "Tidore",
  province: "Maluku Utara",
  capacity: 40
}
```

## 🔄 Workflow Penggunaan

1. **Navigasi** ke menu "Sumber Cangkang Sawit"
2. **Lihat Daftar** sumber yang terdaftar
3. **Tambah Sumber Baru** dengan klik "Tambah Sumber"
4. **Isi Form** dengan data lokasi & stok
5. **Lihat Detail** untuk visualisasi peta
6. **Edit/Hapus** sesuai kebutuhan

## 📞 Support

Untuk pertanyaan atau masalah, hubungi tim development.

---

**Version**: 1.0.0  
**Last Updated**: 2026-07-24
