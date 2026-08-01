<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Selalu dibutuhkan: hak akses, organisasi/divisi, dan akun admin.
        $this->call([
            RolePermissionSeeder::class,
            OrganizationSeeder::class,
        ]);

        // Data contoh modul biomassa dilewati pada instalasi khusus
        // manajemen tugas (APP_MODE=tasks) agar aplikasinya benar-benar bersih.
        if (config('app.mode') !== 'tasks') {
            $this->call([
                PawmPLTUSeeder::class,
                PalmOilSourceSeeder::class,
                UnloadingPointSeeder::class,
                JettyPointSeeder::class,
            ]);
        }

        $this->call([
            TaskDemoSeeder::class,
        ]);
    }
}
