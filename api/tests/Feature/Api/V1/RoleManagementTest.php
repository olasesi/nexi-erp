<?php

use App\Models\User;
use App\Support\PermissionGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Passport::actingAs($this->admin);
});

it('lists roles for the API guard with user counts', function () {
    $role = Role::where('name', 'manager')->firstOrFail();
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->getJson('/api/v1/roles');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonStructure(['data' => [['id', 'name', 'label', 'guard_name', 'permissions', 'users_count']]]);

    collect($response->json('data'))->each(fn ($role) => expect($role['guard_name'])->toBe(PermissionGuard::name()));
});

it('creates a role with permissions', function () {
    $response = $this->postJson('/api/v1/roles', [
        'name' => 'finance',
        'label' => 'Finance Team',
        'permissions' => ['products.view-any', 'products.view'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'finance')
        ->assertJsonPath('data.label', 'Finance Team')
        ->assertJsonPath('data.permissions', ['products.view-any', 'products.view']);
});

it('rejects a duplicate role name', function () {
    $this->postJson('/api/v1/roles', ['name' => 'admin'])->assertUnprocessable();
});

it('rejects a role referencing a missing permission', function () {
    $this->postJson('/api/v1/roles', [
        'name' => 'ghost',
        'permissions' => ['does.not.exist'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);
});

it('shows a role with its permissions and users', function () {
    Role::where('name', 'manager')->firstOrFail()->syncPermissions(['companies.view-any']);
    $manager = Role::where('name', 'manager')->firstOrFail();
    $user = User::factory()->create();
    $user->assignRole($manager);

    $response = $this->getJson("/api/v1/roles/{$manager->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'manager')
        ->assertJsonPath('data.permissions', ['companies.view-any'])
        ->assertJsonPath('data.users_count', 1);
});

it('updates a role including permission sync', function () {
    $role = Role::where('name', 'user')->firstOrFail();

    $this->putJson("/api/v1/roles/{$role->id}", [
        'label' => 'Renamed Role',
        'permissions' => ['contacts.view-any'],
    ])->assertOk()
        ->assertJsonPath('data.label', 'Renamed Role')
        ->assertJsonPath('data.permissions', ['contacts.view-any']);

    expect($role->fresh()->getPermissionNames()->all())->toBe(['contacts.view-any']);
});

it('does not delete the admin role', function () {
    $admin = Role::where('name', 'admin')->firstOrFail();

    $this->deleteJson("/api/v1/roles/{$admin->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    expect(Role::where('name', 'admin')->exists())->toBeTrue();
});

it('does not delete a role assigned to users', function () {
    $role = Role::where('name', 'manager')->firstOrFail();
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->deleteJson("/api/v1/roles/{$role->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    expect(Role::where('name', 'manager')->exists())->toBeTrue();
});

it('deletes an unused role', function () {
    $role = Role::create(['name' => 'temp', 'guard_name' => PermissionGuard::name()]);

    $this->deleteJson("/api/v1/roles/{$role->id}")->assertNoContent();

    expect(Role::where('name', 'temp')->exists())->toBeFalse();
});

it('blocks a user without the matching permission', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);

    $this->getJson('/api/v1/roles')->assertForbidden();
    $this->postJson('/api/v1/roles', ['name' => 'hr'])->assertForbidden();
});

it('allows a user who holds the roles permissions through a role', function () {
    $role = Role::create(['name' => 'hr-admin', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['roles.view-any', 'roles.create', 'roles.update', 'roles.delete']);

    $user = User::factory()->create();
    $user->assignRole($role);
    Passport::actingAs($user);

    $this->getJson('/api/v1/roles')->assertOk();
    $this->postJson('/api/v1/roles', ['name' => 'new-role'])->assertCreated();
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/roles')->assertUnauthorized();
});
