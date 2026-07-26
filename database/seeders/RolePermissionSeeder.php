<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $modules = ['inventory', 'invoice', 'billing', 'books', 'letters'];
        $actions = ['view', 'create', 'edit', 'delete', 'approve'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$module.$action"]);
            }
        }

        $matrix = [
            'super_admin' => collect($modules)->flatMap(fn($m) => collect($actions)->map(fn($a) => "$m.$a"))->toArray(),
            'drafter'     => collect($modules)->flatMap(fn($m) => ["$m.view", "$m.create", "$m.edit"])->toArray(),
            'reviewer'    => collect($modules)->flatMap(fn($m) => ["$m.view", "$m.edit"])->toArray(),
            'approval'    => collect($modules)->flatMap(fn($m) => ["$m.view", "$m.approve"])->toArray(),
            'external'    => collect($modules)->map(fn($m) => "$m.view")->toArray(),
        ];

        foreach ($matrix as $roleName => $permissions) {
            Role::firstOrCreate(['name' => $roleName])->syncPermissions($permissions);
        }
    }
}
