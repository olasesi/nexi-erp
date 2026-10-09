<?php

use App\Models\Company;
use App\Models\HrmDepartment;
use App\Models\HrmDesignation;
use App\Models\HrmEmployee;
use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);

    $this->department = HrmDepartment::factory()->create(['company_id' => $this->company->id]);
});

function hrmDesignationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Operations Lead',
        'department_id' => null,
        'level' => 4,
    ], $overrides);
}

it('lists designations with their department', function () {
    HrmDesignation::factory()->forDepartment($this->department)->create(['company_id' => $this->company->id]);
    HrmDesignation::factory()->create(['company_id' => Company::factory()->create()->id]);

    $response = $this->getJson('/api/v1/hrm-designations');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.department.id', $this->department->id);
});

it('creates a designation inside a department', function () {
    $response = $this->postJson('/api/v1/hrm-designations', hrmDesignationPayload([
        'department_id' => $this->department->id,
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Operations Lead')
        ->assertJsonPath('data.level', 4)
        ->assertJsonPath('data.department_id', $this->department->id);
});

it('validates the designation payload', function () {
    $this->postJson('/api/v1/hrm-designations', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->postJson('/api/v1/hrm-designations', hrmDesignationPayload(['level' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['level']);

    $this->postJson('/api/v1/hrm-designations', hrmDesignationPayload(['department_id' => 999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['department_id']);
});

it('updates a designation', function () {
    $designation = HrmDesignation::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Junior Clerk',
    ]);

    $this->putJson("/api/v1/hrm-designations/{$designation->id}", [
        'name' => 'Senior Clerk',
        'level' => 7,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Senior Clerk')
        ->assertJsonPath('data.level', 7);

    expect($designation->fresh()->name)->toBe('Senior Clerk');
});

it('deletes and restores a designation', function () {
    $designation = HrmDesignation::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/hrm-designations/{$designation->id}")->assertNoContent();
    expect(HrmDesignation::find($designation->id))->toBeNull();

    $this->postJson("/api/v1/hrm-designations/{$designation->id}/restore")->assertOk();
    expect(HrmDesignation::find($designation->id))->not->toBeNull();
});

it('filters designations by department and counts the people holding them', function () {
    $other = HrmDepartment::factory()->create(['company_id' => $this->company->id]);
    $mine = HrmDesignation::factory()->forDepartment($this->department)->create(['company_id' => $this->company->id]);
    HrmDesignation::factory()->forDepartment($other)->create(['company_id' => $this->company->id]);

    HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'designation_id' => $mine->id,
    ]);

    $this->getJson('/api/v1/hrm-designations?department_id='.$this->department->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.employees_count', 1);
});

it('blocks a user without the hrm-designations permissions', function () {
    $staff = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($staff);

    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create([
            'name' => "hrm-designations.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $this->getJson('/api/v1/hrm-designations')->assertForbidden();
    $this->postJson('/api/v1/hrm-designations', hrmDesignationPayload())->assertForbidden();
});
