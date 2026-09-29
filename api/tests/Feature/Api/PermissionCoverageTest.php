<?php

use App\Http\Middleware\EnsurePermitted;
use App\Models\Company;
use App\Models\User;
use App\Support\PermissionGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The permitted middleware skips enforcement when the matching permission row
 * does not exist, so a routed module missing from the seeder silently becomes
 * readable by every staff user. These tests pin that invariant down.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('seeds a permission for every gated route', function () {
    $missing = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }

        $permission = EnsurePermitted::permissionName($route->getName());

        if ($permission === null) {
            continue;
        }

        $exists = Permission::where('name', $permission)
            ->where('guard_name', PermissionGuard::name())
            ->exists();

        if (! $exists) {
            $missing[$route->getName()] = $permission;
        }
    }

    expect($missing)->toBe([]);
});

it('gates the audit trail, currencies and webhooks', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);

    $this->getJson('/api/v1/audit-logs')->assertForbidden();
    $this->getJson('/api/v1/currencies')->assertForbidden();
    $this->getJson('/api/v1/webhook-events')->assertForbidden();
    $this->getJson('/api/v1/webhook-endpoints')->assertForbidden();
    $this->getJson('/api/v1/webhook-deliveries')->assertForbidden();
});

it('keeps the integrations surface away from the base user role', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    Passport::actingAs($user);

    $this->getJson('/api/v1/webhook-endpoints')->assertForbidden();
    $this->getJson('/api/v1/audit-logs')->assertForbidden();
});

it('lets an admin reach the audit trail, currencies and webhooks', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Passport::actingAs($admin);

    $this->getJson('/api/v1/audit-logs')->assertOk();
    $this->getJson('/api/v1/currencies')->assertOk();
    $this->getJson('/api/v1/webhook-events')->assertOk();
    $this->getJson('/api/v1/webhook-endpoints')->assertOk();
    $this->getJson('/api/v1/webhook-deliveries')->assertOk();
});

it('grants the manager role the integrations surface without delete rights', function () {
    $company = Company::factory()->create();

    $manager = User::factory()->create();
    $manager->assignRole('manager');
    Passport::actingAs($manager);

    $this->getJson('/api/v1/webhook-events')->assertOk();
    $this->getJson('/api/v1/audit-logs')->assertOk();
    $this->postJson('/api/v1/webhook-endpoints', [
        'company_id' => $company->id,
        'name' => 'Finance hook',
        'url' => 'https://hooks.example.com/nexi',
        'events' => ['invoice.sent'],
    ])->assertCreated();

    expect($manager->can('webhook-endpoints.create'))->toBeTrue()
        ->and($manager->can('webhook-endpoints.delete'))->toBeFalse();
});
