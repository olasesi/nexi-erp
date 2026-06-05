<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modules = ['companies', 'contacts', 'product-categories', 'products', 'warehouses', 'sales-orders', 'purchase-orders'];

        $actions = ['view-any', 'view', 'create', 'update', 'delete'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => 'web']);
            }
        }

        Permission::firstOrCreate(['name' => 'manage-roles', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage-permissions', 'guard_name' => 'web']);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->syncPermissions(Permission::whereNotIn('name', ['manage-roles', 'manage-permissions', 'delete'])->get());

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $userRole->syncPermissions(Permission::whereIn('name', [
            'contacts.view-any', 'contacts.view', 'contacts.create', 'contacts.update',
            'products.view-any', 'products.view',
            'sales-orders.view-any', 'sales-orders.view', 'sales-orders.create', 'sales-orders.update',
        ])->get());

        $user = User::where('email', 'admin@nexi-corp.com')->first();
        if ($user) {
            $user->assignRole('admin');
        }
    }
}
