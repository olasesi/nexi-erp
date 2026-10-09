<?php

use App\Models\Company;
use App\Models\HrmDepartment;
use App\Models\HrmEmployee;
use App\Models\User;
use App\Support\PermissionGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);
});

function hrmDepartmentPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Operations',
        'code' => 'OPS',
        'description' => 'Day to day running of the business.',
    ], $overrides);
}

it('lists departments for the acting user’s company only', function () {
    HrmDepartment::factory()->create(['company_id' => $this->company->id]);
    HrmDepartment::factory()->create(['company_id' => Company::factory()->create()->id]);

    $response = $this->getJson('/api/v1/hrm-departments');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonCount(1, 'data');
});

it('shows a department with its headcount', function () {
    $department = HrmDepartment::factory()->create(['company_id' => $this->company->id]);
    HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $department->id,
    ]);

    $response = $this->getJson("/api/v1/hrm-departments/{$department->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $department->id)
        ->assertJsonPath('data.name', $department->name)
        ->assertJsonPath('data.employees_count', 1);
});

it('creates a department stamped with the acting user’s company', function () {
    $response = $this->postJson('/api/v1/hrm-departments', hrmDepartmentPayload());

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Operations')
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(HrmDepartment::count())->toBe(1);
});

it('validates the department payload', function () {
    $this->postJson('/api/v1/hrm-departments', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->postJson('/api/v1/hrm-departments', hrmDepartmentPayload(['is_active' => 'maybe']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_active']);
});

it('updates a department', function () {
    $department = HrmDepartment::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Old name',
    ]);

    $response = $this->putJson("/api/v1/hrm-departments/{$department->id}", [
        'name' => 'New name',
        'is_active' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New name')
        ->assertJsonPath('data.is_active', false);

    expect($department->fresh()->name)->toBe('New name');
});

it('deletes and restores a department', function () {
    $department = HrmDepartment::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/hrm-departments/{$department->id}")->assertNoContent();

    expect(HrmDepartment::find($department->id))->toBeNull()
        ->and(HrmDepartment::withTrashed()->find($department->id))->not->toBeNull();

    $this->postJson("/api/v1/hrm-departments/{$department->id}/restore")->assertOk();

    expect(HrmDepartment::find($department->id))->not->toBeNull();
});

it('refuses to delete a department that still holds people', function () {
    $department = HrmDepartment::factory()->create(['company_id' => $this->company->id]);
    HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $department->id,
    ]);

    $this->deleteJson("/api/v1/hrm-departments/{$department->id}")
        ->assertStatus(422);

    expect(HrmDepartment::find($department->id))->not->toBeNull();
});

it('filters departments by is_active and searches by name', function () {
    HrmDepartment::factory()->create(['company_id' => $this->company->id, 'name' => 'Warehouse Team']);
    HrmDepartment::factory()->inactive()->create(['company_id' => $this->company->id, 'name' => 'Design Team']);

    $this->getJson('/api/v1/hrm-departments?is_active=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Design Team');

    $this->getJson('/api/v1/hrm-departments?search=Warehouse')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('blocks staff without the hrm-departments permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $staff = User::factory()->create(['company_id' => $this->company->id]);
    $staff->assignRole('user');
    Passport::actingAs($staff);

    expect($staff->can('hrm-departments.view-any'))->toBeFalse()
        ->and($staff->can('hrm-departments.create'))->toBeFalse();

    $this->getJson('/api/v1/hrm-departments')->assertForbidden();
    $this->postJson('/api/v1/hrm-departments', hrmDepartmentPayload())->assertForbidden();

    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->assignRole('admin');
    Passport::actingAs($admin);

    expect($admin->can('hrm-departments.create'))->toBeTrue();

    $this->postJson('/api/v1/hrm-departments', hrmDepartmentPayload(['name' => 'Finance']))
        ->assertCreated();
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/hrm-departments')->assertUnauthorized();
});

it('seeds the hrm permission modules under the api guard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach ([
        'hrm-employees',
        'hrm-departments',
        'hrm-designations',
        'hrm-attendance',
        'hrm-leaves',
    ] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            expect(Permission::where('name', "{$module}.{$action}")
                ->where('guard_name', PermissionGuard::name())
                ->exists())->toBeTrue();
        }
    }
});
