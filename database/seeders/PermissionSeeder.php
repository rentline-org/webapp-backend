<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Registry\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /** Run the database seeds. */
    public function run(): void
    {
        $permissions = [
            // role
            ...PermissionRegistry::getRolePermissions(),
            // user
            ...PermissionRegistry::getUserPermissions(),

        ];
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        // Permission::insert($permissions);
        Permission::upsert($permissions, ['name', 'guard_name'], ['group', 'description', 'updated_at']);
    }
}
