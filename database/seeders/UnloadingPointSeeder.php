<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\UnloadingPoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnloadingPointSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();
        if (!$org) {
            return;
        }

        $points = [
            [
                'name' => 'Dermaga Bongkar PLTU Tidore',
                'customer_name' => 'PT PLN (Persero)',
                'latitude' => 0.6900, 'longitude' => 127.4000,
                'province' => 'Maluku Utara', 'city' => 'Tidore', 'district' => 'Tidore Utara',
                'address' => 'Pelabuhan Soasio, Tidore',
                'capacity' => 10000, 'unit' => 'ton',
                'pic_name' => 'Ahmad Yani', 'pic_phone' => '0812-1111-2222',
                'notes' => 'Titik bongkar utama tujuan akhir cangkang sawit.',
            ],
            [
                'name' => 'Terminal Bongkar Tarahan',
                'customer_name' => 'PT PLN Sektor Lampung',
                'latitude' => -5.5300, 'longitude' => 105.3200,
                'province' => 'Lampung', 'city' => 'Lampung Selatan', 'district' => 'Katibung',
                'address' => 'Pelabuhan Tarahan, Lampung',
                'capacity' => 8000, 'unit' => 'ton',
                'pic_name' => 'Siti Rahma', 'pic_phone' => '0813-3333-4444',
                'notes' => 'Bongkar untuk co-firing PLTU Tarahan.',
            ],
            [
                'name' => 'Dermaga Bongkar Bintan',
                'customer_name' => 'PT Energi Kepri',
                'latitude' => 1.1500, 'longitude' => 104.7500,
                'province' => 'Kepulauan Riau', 'city' => 'Bintan', 'district' => 'Bintan Timur',
                'address' => 'Pelabuhan Kijang, Bintan',
                'capacity' => 5000, 'unit' => 'ton',
                'pic_name' => 'Rudi Hartono', 'pic_phone' => '0814-5555-6666',
                'notes' => 'Titik bongkar wilayah timur.',
            ],
        ];

        foreach ($points as $p) {
            UnloadingPoint::create([
                'id' => Str::uuid(),
                'organization_id' => $org->id,
                'status' => 'active',
                ...$p,
            ]);
        }
    }
}
