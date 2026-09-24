<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = PermissionGuard::name();

        $this->normalizeGuard('web', $guard);

        $modules = ['companies', 'contacts', 'product-categories', 'products', 'warehouses', 'sales-orders', 'purchase-orders', 'users', 'roles'];

        $actions = ['view-any', 'view', 'create', 'update', 'delete'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => $guard]);
            }
        }

        Permission::firstOrCreate(['name' => 'manage-roles', 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => 'manage-permissions', 'guard_name' => $guard]);

        Permission::firstOrCreate(['name' => 'portal.access', 'guard_name' => $guard]);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $adminRole->syncPermissions(Permission::all());

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => $guard]);
        $managerRole->syncPermissions(
            Permission::where('name', 'not like', '%.delete')
                ->whereNotIn('name', ['manage-roles', 'manage-permissions'])
                ->get()
        );

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard]);
        $userRole->syncPermissions(Permission::whereIn('name', [
            'contacts.view-any', 'contacts.view', 'contacts.create', 'contacts.update',
            'products.view-any', 'products.view',
            'sales-orders.view-any', 'sales-orders.view', 'sales-orders.create', 'sales-orders.update',
        ])->get());

        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => $guard]);
        $customerRole->syncPermissions(['portal.access']);

        $user = User::where('email', 'admin@nexi-corp.com')->first();
        if ($user) {
            $user->assignRole('admin');
        }
    }

    /**
     * Move legacy roles/permissions created under another guard onto the API
     * guard, merging duplicate rows and repointing their pivot links so an
     * existing database never ends up with rows split across two guards.
     */
    private function normalizeGuard(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $permKey = $columns['permission_pivot_key'] ?: 'permission_id';
        $roleKey = $columns['role_pivot_key'] ?: 'role_id';
        $morphKey = $columns['model_morph_key'] ?: 'model_id';

        Permission::where('guard_name', $from)->get()->each(function (Permission $permission) use ($to, $tables, $permKey, $roleKey, $morphKey) {
            $existing = Permission::where('name', $permission->name)->where('guard_name', $to)->first();

            if (! $existing) {
                $permission->update(['guard_name' => $to]);

                return;
            }

            $this->mergePivot($tables['role_has_permissions'], $permKey, $permission->id, $existing->id, [$roleKey]);
            $this->mergePivot($tables['model_has_permissions'], $permKey, $permission->id, $existing->id, [$morphKey, 'model_type']);
            $permission->delete();
        });

        Role::where('guard_name', $from)->get()->each(function (Role $role) use ($to, $tables, $permKey, $roleKey, $morphKey) {
            $existing = Role::where('name', $role->name)->where('guard_name', $to)->first();

            if (! $existing) {
                $role->update(['guard_name' => $to]);

                return;
            }

            $this->mergePivot($tables['role_has_permissions'], $roleKey, $role->id, $existing->id, [$permKey]);
            $this->mergePivot($tables['model_has_roles'], $roleKey, $role->id, $existing->id, [$morphKey, 'model_type']);
            $role->delete();
        });
    }

    /**
     * Move pivot rows keyed by $key from $fromId to $toId, dropping rows whose
     * target already exists (composite keys would otherwise collide).
     */
    private function mergePivot(string $table, string $key, int $fromId, int $toId, array $partnerCols): void
    {
        foreach (DB::table($table)->where($key, $fromId)->get() as $row) {
            $target = DB::table($table)->where($key, $toId);
            $legacy = DB::table($table)->where($key, $fromId);

            foreach ($partnerCols as $col) {
                $target->where($col, $row->{$col});
                $legacy->where($col, $row->{$col});
            }

            if ($target->exists()) {
                $legacy->delete();
            } else {
                $legacy->update([$key => $toId]);
            }
        }
    }
}
