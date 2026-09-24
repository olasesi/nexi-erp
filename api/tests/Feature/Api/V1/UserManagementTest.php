<?php

use App\Models\Company;
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

    $this->company = Company::factory()->create();

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');
    Passport::actingAs($this->admin);
});

it('lists users with roles', function () {
    User::factory()->count(3)->create(['company_id' => $this->company->id]);

    $response = $this->getJson('/api/v1/users');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'username', 'phone', 'is_active', 'roles']]]);
});

it('creates a user with roles', function () {
    $response = $this->postJson('/api/v1/users', [
        'username' => 'jdoe',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+15550123',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['user'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonPath('data.username', 'jdoe')
        ->assertJsonPath('data.roles', ['user'])
        ->assertJsonPath('data.company_id', $this->company->id);

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'username' => 'jdoe']);
    expect(User::where('email', 'jane@example.com')->first()->hasRole('user'))->toBeTrue();
});

it('rejects a user with a duplicate email', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->postJson('/api/v1/users', [
        'name' => 'Dup',
        'email' => 'dup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects a user with a duplicate username', function () {
    User::factory()->create(['username' => 'taken']);

    $this->postJson('/api/v1/users', [
        'username' => 'taken',
        'name' => 'Dup',
        'email' => 'new@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);
});

it('rejects a user with a role that does not exist', function () {
    $this->postJson('/api/v1/users', [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['nonexistent'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['roles.0']);
});

it('shows a user with their roles', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole('manager');

    $this->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles', ['manager']);
});

it('updates a user including role changes and password', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole('user');

    $this->putJson("/api/v1/users/{$user->id}", [
        'name' => 'Renamed',
        'phone' => '+15559999',
        'roles' => ['manager'],
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.phone', '+15559999')
        ->assertJsonPath('data.roles', ['manager']);

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('Renamed')
        ->and($fresh->hasRole('manager'))->toBeTrue()
        ->and($fresh->hasRole('user'))->toBeFalse();
});

it('deactivates a user via is_active', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);

    $this->putJson("/api/v1/users/{$user->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($user->fresh()->is_active)->toBeFalse();
});

it('deletes a user', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole('user');

    $this->deleteJson("/api/v1/users/{$user->id}")->assertNoContent();

    expect(User::find($user->id))->toBeNull();
    expect($user->roles()->count())->toBe(0);
});

it('does not delete your own account', function () {
    $this->deleteJson("/api/v1/users/{$this->admin->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('does not delete the last administrator', function () {
    $hr = Role::create(['name' => 'hr-admin', 'guard_name' => PermissionGuard::name()]);
    $hr->givePermissionTo('users.delete');

    $actor = User::factory()->create(['company_id' => $this->company->id]);
    $actor->assignRole($hr);
    Passport::actingAs($actor);

    $onlyAdmin = User::factory()->create(['company_id' => $this->company->id]);
    $onlyAdmin->assignRole('admin');
    $this->admin->delete();

    $this->deleteJson("/api/v1/users/{$onlyAdmin->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);
});

it('blocks a user without the matching permission', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($user);

    $this->getJson('/api/v1/users')->assertForbidden();
    $this->postJson('/api/v1/users', [
        'name' => 'Nope',
        'email' => 'nope@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertForbidden();
});

it('allows a user who holds the users permissions through a role', function () {
    $role = Role::create(['name' => 'hr', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['users.view-any', 'users.create']);

    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole($role);
    Passport::actingAs($user);

    $this->getJson('/api/v1/users')->assertOk();
    $this->postJson('/api/v1/users', [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();
});

it('filters users by is_active', function () {
    User::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
    User::factory()->create(['company_id' => $this->company->id, 'is_active' => false]);

    $response = $this->getJson('/api/v1/users?is_active=0');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('is_active'))->each->toBeFalse();
});

it('filters users by role', function () {
    $manager = User::factory()->create(['company_id' => $this->company->id]);
    $manager->assignRole('manager');

    $response = $this->getJson('/api/v1/users?role='.urlencode('manager'));

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.id'))->toBe($manager->id);
});

it('searches users by name and email', function () {
    User::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com', 'company_id' => $this->company->id]);
    User::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com', 'company_id' => $this->company->id]);

    $response = $this->getJson('/api/v1/users?search=Alice');
    $response->assertOk();
    expect($response->json('data.0.name'))->toBe('Alice Smith');

    $response = $this->getJson('/api/v1/users?search=alice@example.com');
    $response->assertOk();
    expect($response->json('data.0.email'))->toBe('alice@example.com');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/users')->assertUnauthorized();
});
