<?php

use App\Models\Company;
use App\Models\HrmAttendance;
use App\Models\HrmDepartment;
use App\Models\HrmEmployee;
use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);

    $department = HrmDepartment::factory()->create(['company_id' => $this->company->id]);
    $this->employee = HrmEmployee::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $department->id,
    ]);
});

function hrmAttendancePayload(array $overrides = []): array
{
    return array_merge([
        'employee_id' => 0,
        'attendance_date' => now()->toDateString(),
        'status' => 'present',
        'check_in' => '09:00',
        'check_out' => '17:30',
    ], $overrides);
}

it('lists attendance newest first with the employee attached', function () {
    HrmAttendance::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'attendance_date' => now()->subDay()->toDateString(),
    ]);
    HrmAttendance::factory()->onDate(now()->toDateString())->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $response = $this->getJson('/api/v1/hrm-attendance');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.employee.id', $this->employee->id);
});

it('creates a day sheet stamped with the acting user and derived hours', function () {
    $response = $this->postJson('/api/v1/hrm-attendance', hrmAttendancePayload([
        'employee_id' => $this->employee->id,
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id)
        ->assertJsonPath('data.status', 'present');

    expect((float) $response->json('data.work_hours'))->toBe(8.5);
});

it('keeps an explicit work_hours total', function () {
    $response = $this->postJson('/api/v1/hrm-attendance', hrmAttendancePayload([
        'employee_id' => $this->employee->id,
        'work_hours' => 4,
    ]));

    $response->assertCreated();

    expect((float) $response->json('data.work_hours'))->toBe(4.0);
});

it('rolls an overnight shift over midnight', function () {
    $response = $this->postJson('/api/v1/hrm-attendance', hrmAttendancePayload([
        'employee_id' => $this->employee->id,
        'check_in' => '22:00',
        'check_out' => '06:00',
    ]));

    $response->assertCreated();

    expect((float) $response->json('data.work_hours'))->toBe(8.0);
});

it('only allows one sheet per employee per day', function () {
    HrmAttendance::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'attendance_date' => now()->toDateString(),
    ]);

    $this->postJson('/api/v1/hrm-attendance', hrmAttendancePayload([
        'employee_id' => $this->employee->id,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_date']);

    $other = HrmEmployee::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/hrm-attendance', hrmAttendancePayload([
        'employee_id' => $other->id,
    ]))->assertCreated();
});

it('validates the attendance payload', function () {
    $this->postJson('/api/v1/hrm-attendance', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id', 'attendance_date']);

    $this->postJson('/api/v1/hrm-attendance', hrmAttendancePayload([
        'employee_id' => $this->employee->id,
        'status' => 'sleeping',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->postJson('/api/v1/hrm-attendance', hrmAttendancePayload([
        'employee_id' => $this->employee->id,
        'check_in' => '9am',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['check_in']);
});

it('recomputes the hours when the punch times move', function () {
    $attendance = HrmAttendance::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'attendance_date' => now()->toDateString(),
        'check_in' => '09:00',
        'check_out' => '17:00',
        'work_hours' => 8,
    ]);

    $this->putJson("/api/v1/hrm-attendance/{$attendance->id}", ['check_out' => '18:00'])
        ->assertOk()
        ->assertJsonPath('data.check_out', '18:00');

    expect((float) $attendance->fresh()->work_hours)->toBe(9.0);

    $this->putJson("/api/v1/hrm-attendance/{$attendance->id}", ['status' => 'late'])
        ->assertOk()
        ->assertJsonPath('data.status', 'late');

    expect((float) $attendance->fresh()->work_hours)->toBe(9.0);
});

it('filters attendance by date and status', function () {
    HrmAttendance::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'attendance_date' => now()->toDateString(),
        'status' => HrmAttendance::STATUS_PRESENT,
    ]);
    HrmAttendance::factory()->absent()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'attendance_date' => now()->subDay()->toDateString(),
    ]);

    $this->getJson('/api/v1/hrm-attendance?status=absent')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'absent');

    $this->getJson('/api/v1/hrm-attendance?attendance_date='.now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('deletes an attendance row outright', function () {
    $attendance = HrmAttendance::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->deleteJson("/api/v1/hrm-attendance/{$attendance->id}")->assertNoContent();

    expect(HrmAttendance::find($attendance->id))->toBeNull()
        ->and(HrmAttendance::query()->whereKey($attendance->id)->exists())->toBeFalse();
});

it('blocks staff without the hrm-attendance permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "hrm-attendance.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $staff = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($staff);

    $this->getJson('/api/v1/hrm-attendance')->assertForbidden();
    $this->postJson('/api/v1/hrm-attendance', hrmAttendancePayload([
        'employee_id' => $this->employee->id,
    ]))->assertForbidden();
});
