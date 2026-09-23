<?php

use App\Models\Company;
use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "companies.{$action}", 'guard_name' => PermissionGuard::name()]);
    }
});

it('blocks a user without the matching permission', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);

    $this->postJson('/api/v1/companies', ['name' => 'Acme'])
        ->assertForbidden();
});

it('allows a user who holds the permission through a role', function () {
    $role = Role::create(['name' => 'manager', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo('companies.create');

    $user = User::factory()->create();
    $user->assignRole($role);
    Passport::actingAs($user);

    $this->postJson('/api/v1/companies', ['name' => 'Acme'])
        ->assertCreated();
});

it('enforces each action of a resource independently', function () {
    $role = Role::create(['name' => 'clerk', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo('companies.create');
    $company = Company::factory()->create();

    $user = User::factory()->create();
    $user->assignRole($role);
    Passport::actingAs($user);

    $this->getJson('/api/v1/companies')->assertForbidden();
    $this->getJson("/api/v1/companies/{$company->id}")->assertForbidden();
    $this->postJson('/api/v1/companies', ['name' => 'Acme'])->assertCreated();
    $this->putJson("/api/v1/companies/{$company->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->deleteJson("/api/v1/companies/{$company->id}")->assertForbidden();
});

it('lets an admin role perform every action', function () {
    $admin = Role::create(['name' => 'admin', 'guard_name' => PermissionGuard::name()]);
    $admin->syncPermissions(Permission::all());
    $company = Company::factory()->create();

    $user = User::factory()->create();
    $user->assignRole($admin);
    Passport::actingAs($user);

    $this->getJson('/api/v1/companies')->assertOk();
    $this->getJson("/api/v1/companies/{$company->id}")->assertOk();
    $this->putJson("/api/v1/companies/{$company->id}", ['name' => 'Renamed'])->assertOk();
    $this->deleteJson("/api/v1/companies/{$company->id}")->assertNoContent();
});

it('keeps routes without a seeded permission open', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    Passport::actingAs($user);

    $this->getJson('/api/v1/dashboard?company_id='.$company->id)->assertOk();
    $this->getJson('/api/v1/currencies')->assertOk();
});
