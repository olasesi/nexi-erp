<?php

use App\Models\Company;
use App\Models\HrmAttendance;
use App\Models\HrmDepartment;
use App\Models\HrmDesignation;
use App\Models\HrmEmployee;
use App\Models\HrmLeave;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);

    $this->department = HrmDepartment::factory()->create(['company_id' => $this->company->id]);
    $this->designation = HrmDesignation::factory()->create(['company_id' => $this->company->id]);
});

function hrmEmployeePayload(array $overrides = []): array
{
    return array_merge([
        'employee_code' => 'EMP-0001',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'employment_status' => HrmEmployee::STATUS_ACTIVE,
        'date_of_joining' => now()->subYear()->toDateString(),
    ], $overrides);
}

it('lists employees for the acting user’s company only', function () {
    HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
    ]);
    HrmEmployee::factory()->create(['company_id' => Company::factory()->create()->id]);

    $response = $this->getJson('/api/v1/hrm-employees');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.department.id', $this->department->id);
});

it('creates an employee stamped with the acting user’s company', function () {
    $response = $this->postJson('/api/v1/hrm-employees', hrmEmployeePayload([
        'department_id' => $this->department->id,
        'designation_id' => $this->designation->id,
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.employee_code', 'EMP-0001')
        ->assertJsonPath('data.full_name', 'Ada Lovelace')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.department_id', $this->department->id)
        ->assertJsonPath('data.employment_status', 'active');

    expect(HrmEmployee::count())->toBe(1);
});

it('validates the employee payload', function () {
    $this->postJson('/api/v1/hrm-employees', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_code', 'first_name']);

    $this->postJson('/api/v1/hrm-employees', hrmEmployeePayload(['employment_status' => 'retired']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_status']);
});

it('rejects a duplicate employee code inside the same company', function () {
    HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'employee_code' => 'EMP-0001',
    ]);

    $this->postJson('/api/v1/hrm-employees', hrmEmployeePayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_code']);
});

it('keeps employee codes unique per company, not globally', function () {
    HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'employee_code' => 'EMP-0001',
    ]);

    $otherCompany = Company::factory()->create();
    Passport::actingAs(User::factory()->create(['company_id' => $otherCompany->id]));

    $this->postJson('/api/v1/hrm-employees', hrmEmployeePayload())
        ->assertCreated()
        ->assertJsonPath('data.company_id', $otherCompany->id);
});

it('updates an employee', function () {
    $employee = HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ]);

    $this->putJson("/api/v1/hrm-employees/{$employee->id}", [
        'employment_status' => HrmEmployee::STATUS_ON_LEAVE,
        'department_id' => null,
    ])->assertOk()
        ->assertJsonPath('data.employment_status', 'on_leave')
        ->assertJsonPath('data.department_id', null);

    expect($employee->fresh()->employment_status)->toBe('on_leave');
});

it('deletes and restores an employee', function () {
    $employee = HrmEmployee::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/hrm-employees/{$employee->id}")->assertNoContent();
    expect(HrmEmployee::find($employee->id))->toBeNull();

    $this->postJson("/api/v1/hrm-employees/{$employee->id}/restore")->assertOk();
    expect(HrmEmployee::find($employee->id))->not->toBeNull();
});

it('filters employees by status and searches by name', function () {
    HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
    HrmEmployee::factory()->resigned()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
    ]);

    $this->getJson('/api/v1/hrm-employees?employment_status=resigned')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employment_status', 'resigned');

    $this->getJson('/api/v1/hrm-employees?department_id='.$this->department->id)
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/hrm-employees?search=Lovelace')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('serves a dashboard ready summary', function () {
    $employee = HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'employment_status' => HrmEmployee::STATUS_ACTIVE,
    ]);

    HrmAttendance::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $employee->id,
        'attendance_date' => now()->toDateString(),
        'status' => HrmAttendance::STATUS_PRESENT,
    ]);

    HrmLeave::factory()->approved()
        ->spanning(now()->toDateString(), now()->toDateString(), 1)
        ->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
        ]);

    HrmLeave::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $employee->id,
    ]);

    $response = $this->getJson('/api/v1/hrm-employees/summary?company_id='.$this->company->id);

    $response->assertOk()
        ->assertJsonPath('company_id', $this->company->id)
        ->assertJsonPath('as_of', now()->toDateString())
        ->assertJsonPath('total_employees', 1)
        ->assertJsonPath('working_employees', 1)
        ->assertJsonPath('by_status.active', 1)
        ->assertJsonPath('by_department.0.count', 1)
        ->assertJsonPath('by_department.0.department_id', $this->department->id)
        ->assertJsonPath('attendance_today.present', 1)
        ->assertJsonPath('attendance_today.absent', 0)
        ->assertJsonPath('attendance_today.missing', 0)
        ->assertJsonPath('on_leave_today', 1)
        ->assertJsonPath('pending_leaves', 1)
        ->assertJsonPath('data.recent_employees.0.id', $employee->id);

    expect((float) $response->json('attendance_rate'))->toBe(1.0);
});

it('falls back to the acting user’s company on the summary', function () {
    $response = $this->getJson('/api/v1/hrm-employees/summary');

    $response->assertOk()
        ->assertJsonPath('company_id', $this->company->id)
        ->assertJsonPath('total_employees', 0)
        ->assertJsonPath('attendance_today.missing', 0);
});

it('blocks staff without the hrm-employees permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $staff = User::factory()->create(['company_id' => $this->company->id]);
    $staff->assignRole('user');
    Passport::actingAs($staff);

    expect($staff->can('hrm-employees.view-any'))->toBeFalse();

    $this->getJson('/api/v1/hrm-employees')->assertForbidden();
    $this->getJson('/api/v1/hrm-employees/summary?company_id='.$this->company->id)->assertForbidden();
    $this->postJson('/api/v1/hrm-employees', hrmEmployeePayload())->assertForbidden();
});
