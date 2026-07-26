<?php

namespace Database\Seeders;

use App\Models\JettyPoint;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JettyPointSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();
        if (!$org) {
            return;
        }

        $jetties = [
            [
                'name' => 'Dermaga Soasio Tidore', 'operator' => 'PT Pelindo',
                'latitude' => 0.6850, 'longitude' => 127.4100,
                'province' => 'Maluku Utara', 'city' => 'Tidore', 'district' => 'Tidore Utara',
                'address' => 'Pelabuhan Soasio', 'capacity' => 6000, 'unit' => 'ton',
                'price' => 55000, 'draft' => '-6 m LWS',
                'pic_name' => 'Hasan', 'pic_phone' => '0812-7777-8888',
            ],
            [
                'name' => 'Dermaga Panjang Lampung', 'operator' => 'PT Pelindo Regional 2',
                'latitude' => -5.4700, 'longitude' => 105.3100,
                'province' => 'Lampung', 'city' => 'Bandar Lampung', 'district' => 'Panjang',
                'address' => 'Pelabuhan Panjang', 'capacity' => 8000, 'unit' => 'ton',
                'price' => 48000, 'draft' => '-8 m LWS',
                'pic_name' => 'Wawan', 'pic_phone' => '0813-9999-0000',
            ],
            [
                'name' => 'Dermaga Kijang Bintan', 'operator' => 'PT Pelabuhan Bintan',
                'latitude' => 0.8600, 'longitude' => 104.6200,
                'province' => 'Kepulauan Riau', 'city' => 'Bintan', 'district' => 'Bintan Timur',
                'address' => 'Pelabuhan Kijang', 'capacity' => 5000, 'unit' => 'ton',
                'price' => 52000, 'draft' => '-7 m LWS',
                'pic_name' => 'Rizal', 'pic_phone' => '0811-2222-3333',
            ],
        ];

        foreach ($jetties as $j) {
            JettyPoint::create([
                'id' => Str::uuid(),
                'organization_id' => $org->id,
                'status' => 'active',
                ...$j,
            ]);
        }
    }
}
