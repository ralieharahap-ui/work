<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            OrganizationSeeder::class,
            PawmPLTUSeeder::class,
            PalmOilSourceSeeder::class,
            UnloadingPointSeeder::class,
            JettyPointSeeder::class,
        ]);
    }
}
