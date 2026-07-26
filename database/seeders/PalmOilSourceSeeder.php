<?php

namespace Database\Seeders;

use App\Models\PalmOilSource;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PalmOilSourceSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();

        if (!$org) {
            return;
        }

        $sources = [
            [
                'name' => 'Sumber PKS Palalawan Utama',
                'supplier_name' => 'PT Biomassa Riau',
                'latitude' => -0.789275,
                'longitude' => 113.921327,
                'province' => 'Riau',
                'city' => 'Palalawan',
                'district' => 'Sungai Apit',
                'address' => 'Jl. Merdeka No. 123, Palalawan',
                'stock_volume' => 5000,
                'unit' => 'ton',
                'stock_min' => 500,
                'status' => 'active',
                'notes' => 'Sumber utama dengan produksi stabil sepanjang tahun',
            ],
            [
                'name' => 'Sumber Biomassa Pasaman',
                'supplier_name' => 'PT Industri Sawit Pasaman',
                'latitude' => -0.5,
                'longitude' => 100.8,
                'province' => 'Sumatera Barat',
                'city' => 'Pasaman Barat',
                'district' => 'Pasaman',
                'address' => 'Jl. Ahmad Yani No. 456',
                'stock_volume' => 3000,
                'unit' => 'ton',
                'stock_min' => 300,
                'status' => 'active',
                'notes' => 'Sumber sekunder dengan kapasitas produksi menengah',
            ],
            [
                'name' => 'Sumber Cangkang Jambi Timur',
                'supplier_name' => 'PT Sawit Merah',
                'latitude' => -1.45,
                'longitude' => 103.65,
                'province' => 'Jambi',
                'city' => 'Muara Jambi',
                'district' => 'Sungai Gelam',
                'address' => 'Jl. Raya Jambi-Palembang KM 25',
                'stock_volume' => 4500,
                'unit' => 'ton',
                'stock_min' => 450,
                'status' => 'active',
                'notes' => 'Lokasi strategis dekat dengan akses transportasi utama',
            ],
            [
                'name' => 'Sumber PKS Lampung',
                'supplier_name' => 'PT Lampung Biomassa',
                'latitude' => -5.45,
                'longitude' => 105.27,
                'province' => 'Lampung',
                'city' => 'Lampung',
                'district' => 'Tarahan',
                'address' => 'Jl. Diponegoro No. 789, Tarahan',
                'stock_volume' => 2000,
                'unit' => 'ton',
                'stock_min' => 200,
                'status' => 'active',
                'notes' => 'Sumber baru dengan potensi pertumbuhan tinggi',
            ],
            [
                'name' => 'Sumber Cangkang NTB',
                'supplier_name' => 'PT Sawit Nusantara',
                'latitude' => -8.73,
                'longitude' => 115.97,
                'province' => 'Nusa Tenggara Barat',
                'city' => 'Lombok Barat',
                'district' => 'Gerung',
                'address' => 'Jl. Merdeka No. 999, Gerung',
                'stock_volume' => 1500,
                'unit' => 'ton',
                'stock_min' => 150,
                'status' => 'active',
                'notes' => 'Sumber di region timur dengan kondisi produksi stabil',
            ],
            [
                'name' => 'Sumber Cadangan Bintan',
                'supplier_name' => 'PT Riau Sawit',
                'latitude' => 1.1333,
                'longitude' => 104.7333,
                'province' => 'Kepulauan Riau',
                'city' => 'Bintan',
                'district' => 'Bintan Timur',
                'address' => 'Pulau Bintan, Desa Berakit',
                'stock_volume' => 800,
                'unit' => 'ton',
                'stock_min' => 100,
                'status' => 'active',
                'notes' => 'Sumber penunjang dengan kapasitas terbatas',
            ],
            [
                'name' => 'Sumber Maluku Utara',
                'supplier_name' => 'PT Maluku Biomassa',
                'latitude' => 0.6667,
                'longitude' => 127.3833,
                'province' => 'Maluku Utara',
                'city' => 'Tidore',
                'district' => 'Tidore Tengah',
                'address' => 'Jl. Pattimura No. 123, Tidore',
                'stock_volume' => 1200,
                'unit' => 'ton',
                'stock_min' => 150,
                'status' => 'active',
                'notes' => 'Lokasi paling timur dengan pertumbuhan produksi',
            ],
            [
                'name' => 'Sumber PKS Tebing Tinggi',
                'supplier_name' => 'PT Tebing Tinggi Sawit',
                'latitude' => 2.3333,
                'longitude' => 99.1667,
                'province' => 'Sumatera Utara',
                'city' => 'Serdang Bedagai',
                'district' => 'Tebing Tinggi',
                'address' => 'Jl. Kesehatan No. 456, Tebing Tinggi',
                'stock_volume' => 3500,
                'unit' => 'ton',
                'stock_min' => 350,
                'status' => 'active',
                'notes' => 'Sumber utama di Sumatera Utara',
            ],
        ];

        foreach ($sources as $source) {
            PalmOilSource::create([
                'id' => Str::uuid(),
                'organization_id' => $org->id,
                ...$source,
            ]);
        }
    }
}
