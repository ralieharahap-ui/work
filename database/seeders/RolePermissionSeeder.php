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

        $modules = ['inventory', 'invoice', 'billing', 'books', 'letters', 'tasks'];
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

        // ── Permission granular per sub-menu (aditif — tidak menggantikan permission modul kasar di atas) ──
        // Sub-menu "biasa": mengikuti akses books.view lama (semua role yang tadinya bisa lihat /books/* tetap bisa).
        $booksViewSubmenus = ['accounts', 'journal', 'ledger', 'vendors', 'customers', 'assets', 'reports'];
        // Sub-menu baru & lebih sensitif (kreditur pendanaan & kontrol PPN): default hanya role yang berhubungan finance.
        $booksRestrictedSubmenus = ['creditors', 'tax'];

        foreach ($booksViewSubmenus as $sub) {
            Permission::firstOrCreate(['name' => "books.$sub.view"]);
        }
        foreach ($booksRestrictedSubmenus as $sub) {
            Permission::firstOrCreate(['name' => "books.$sub.view"]);
        }
        Permission::firstOrCreate(['name' => 'books.creditors.manage']);

        $granular = [
            'super_admin' => collect($booksViewSubmenus)->concat($booksRestrictedSubmenus)
                ->map(fn ($s) => "books.$s.view")->push('books.creditors.manage')->toArray(),
            'drafter'     => collect($booksViewSubmenus)->map(fn ($s) => "books.$s.view")->toArray(),
            'reviewer'    => collect($booksViewSubmenus)->concat($booksRestrictedSubmenus)
                ->map(fn ($s) => "books.$s.view")->toArray(),
            'approval'    => collect($booksViewSubmenus)->concat($booksRestrictedSubmenus)
                ->map(fn ($s) => "books.$s.view")->toArray(),
            'external'    => collect($booksViewSubmenus)->map(fn ($s) => "books.$s.view")->toArray(),
        ];

        foreach ($matrix as $roleName => $permissions) {
            $all = array_values(array_unique(array_merge($permissions, $granular[$roleName] ?? [])));
            Role::firstOrCreate(['name' => $roleName])->syncPermissions($all);
        }
    }
}
