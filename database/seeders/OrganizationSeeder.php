<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'pt-gep'],
            ['name' => 'PT Geosys Energi Prima']
        );

        $dirUtama = Division::firstOrCreate(['code' => 'DIR-UTM'], ['name' => 'Direktur Utama',       'organization_id' => $org->id, 'parent_id' => null]);
        $dirOps   = Division::firstOrCreate(['code' => 'DIR-OPS'], ['name' => 'Direktur Operasional', 'organization_id' => $org->id, 'parent_id' => null]);

        foreach (['Finance & Tax' => 'FIN', 'Legal & Compliance' => 'LEG', 'Sales' => 'SLS', 'HR' => 'HR'] as $name => $code) {
            Division::firstOrCreate(['code' => $code], ['name' => $name, 'organization_id' => $org->id, 'parent_id' => $dirUtama->id]);
        }
        foreach (['Procurement' => 'PROC', 'Operation' => 'OPS', 'MR' => 'MR', 'K3' => 'K3'] as $name => $code) {
            Division::firstOrCreate(['code' => $code], ['name' => $name, 'organization_id' => $org->id, 'parent_id' => $dirOps->id]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@pt-gep.com'],
            [
                'name'            => 'Administrator',
                'password'        => Hash::make('Admin@12345'),
                'organization_id' => $org->id,
                'hierarchy'       => 'administrator',
                'is_active'       => true,
            ]
        );
        $admin->assignRole('super_admin');
    }
}
