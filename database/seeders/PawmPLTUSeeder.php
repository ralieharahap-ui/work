<?php

namespace Database\Seeders;

use App\Models\PawmPLTU;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PawmPLTUSeeder extends Seeder
{
    public function run(): void
    {
        $pltus = [
            [
                'name' => 'PLTU Sofifi & Tidore',
                'province' => 'Maluku Utara',
                'city' => 'Tidore',
                'latitude' => 0.6667,
                'longitude' => 127.3833,
                'capacity' => 40,
                'fuel_type' => 'biomassa',
                'operator' => 'PT PLN',
                'status' => 'operational',
            ],
            [
                'name' => 'PLTU Moa-Kusambi',
                'province' => 'Maluku Utara',
                'city' => 'Halmahera Selatan',
                'latitude' => 0.5,
                'longitude' => 127.3,
                'capacity' => 60,
                'fuel_type' => 'biomassa',
                'operator' => 'PT PLN',
                'status' => 'operational',
            ],
            [
                'name' => 'PLTU Riau',
                'province' => 'Riau',
                'city' => 'Palalawan',
                'latitude' => -0.789275,
                'longitude' => 113.921327,
                'capacity' => 150,
                'fuel_type' => 'biomassa',
                'operator' => 'PT PLN',
                'status' => 'operational',
            ],
            [
                'name' => 'PLTU Kotabumi',
                'province' => 'Lampung',
                'city' => 'Lampung Utara',
                'latitude' => -4.8333,
                'longitude' => 104.7667,
                'capacity' => 110,
                'fuel_type' => 'biomassa',
                'operator' => 'PT PLN',
                'status' => 'operational',
            ],
            [
                'name' => 'PLTU Tarahan',
                'province' => 'Lampung',
                'city' => 'Lampung',
                'latitude' => -5.45,
                'longitude' => 105.27,
                'capacity' => 135,
                'fuel_type' => 'biomassa',
                'operator' => 'PT PLN',
                'status' => 'operational',
            ],
            [
                'name' => 'PLTU Jeranjang',
                'province' => 'Nusa Tenggara Barat',
                'city' => 'Lombok Barat',
                'latitude' => -8.73,
                'longitude' => 115.97,
                'capacity' => 40,
                'fuel_type' => 'biomassa',
                'operator' => 'PT PLN',
                'status' => 'operational',
            ],
            [
                'name' => 'PLTU Rantau Panjang',
                'province' => 'Jambi',
                'city' => 'Muara Jambi',
                'latitude' => -1.45,
                'longitude' => 103.65,
                'capacity' => 50,
                'fuel_type' => 'biomassa',
                'operator' => 'PT PLN',
                'status' => 'operational',
            ],
            [
                'name' => 'PLTU Bintan',
                'province' => 'Kepulauan Riau',
                'city' => 'Bintan',
                'latitude' => 1.1333,
                'longitude' => 104.7333,
                'capacity' => 70,
                'fuel_type' => 'biomassa',
                'operator' => 'PT PLN',
                'status' => 'operational',
            ],
        ];

        foreach ($pltus as $pltu) {
            PawmPLTU::create([
                'id' => Str::uuid(),
                ...$pltu,
            ]);
        }
    }
}
