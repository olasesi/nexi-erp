<?php

use App\Models\Company;
use App\Models\HrmDepartment;
use App\Models\HrmEmployee;
use App\Models\HrmLeave;
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

function hrmLeavePayload(array $overrides = []): array
{
    return array_merge([
        'employee_id' => 0,
        'leave_type' => 'annual',
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'reason' => 'Family trip.',
    ], $overrides);
}

it('lists leaves for the company newest first with the employee attached', function () {
    HrmLeave::factory()->spanning(now()->addDay()->toDateString(), now()->addDay()->toDateString(), 1)
        ->create(['company_id' => $this->company->id, 'employee_id' => $this->employee->id]);
    HrmLeave::factory()->spanning(now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString(), 3)
        ->create(['company_id' => $this->company->id, 'employee_id' => $this->employee->id]);
    HrmLeave::factory()->create(['company_id' => Company::factory()->create()->id]);

    $response = $this->getJson('/api/v1/hrm-leaves');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.days', 3)
        ->assertJsonPath('data.0.employee.id', $this->employee->id);
});

it('creates a pending leave and counts the days inclusively', function () {
    $start = now()->addWeek()->toDateString();
    $end = now()->addDays(9)->toDateString();

    $response = $this->postJson('/api/v1/hrm-leaves', hrmLeavePayload([
        'employee_id' => $this->employee->id,
        'start_date' => $start,
        'end_date' => $end,
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.start_date', $start)
        ->assertJsonPath('data.end_date', $end)
        ->assertJsonPath('data.reviewed_by', null)
        ->assertJsonPath('data.reviewed_at', null);

    expect((float) $response->json('data.days'))->toBe(3.0);
    expect(HrmLeave::count())->toBe(1);
});

it('stamps the reviewer on a leave that arrives already decided', function () {
    $response = $this->postJson('/api/v1/hrm-leaves', hrmLeavePayload([
        'employee_id' => $this->employee->id,
        'status' => HrmLeave::STATUS_APPROVED,
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewed_by', $this->user->id);

    expect($response->json('data.reviewed_at'))->not->toBeNull();
});

it('validates the leave payload', function () {
    $this->postJson('/api/v1/hrm-leaves', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id', 'start_date', 'end_date']);

    $this->postJson('/api/v1/hrm-leaves', hrmLeavePayload([
        'employee_id' => $this->employee->id,
        'end_date' => now()->addDays(3)->toDateString(),
        'start_date' => now()->addWeek()->toDateString(),
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);

    $this->postJson('/api/v1/hrm-leaves', hrmLeavePayload([
        'employee_id' => $this->employee->id,
        'leave_type' => 'sabbatical',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['leave_type']);
});

it('recomputes the days when both dates move', function () {
    $leave = HrmLeave::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'days' => 1,
    ]);

    $this->putJson("/api/v1/hrm-leaves/{$leave->id}", [
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
    ])->assertOk()
        ->assertJsonPath('data.days', 4);

    expect((float) $leave->fresh()->days)->toBe(4.0);
});

it('approves a pending leave with the reviewer trail stamped', function () {
    $leave = HrmLeave::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $response = $this->postJson("/api/v1/hrm-leaves/{$leave->id}/approve");

    $response->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewed_by', $this->user->id);

    expect($response->json('data.reviewed_at'))->not->toBeNull();
    expect($leave->fresh()->reviewed_by)->toBe($this->user->id);
});

it('rejects a pending leave and keeps the reason', function () {
    $leave = HrmLeave::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->postJson("/api/v1/hrm-leaves/{$leave->id}/reject", [
        'review_note' => 'Release week is frozen.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_note', 'Release week is frozen.');

    expect($leave->fresh()->reviewed_by)->toBe($this->user->id);
});

it('will not review the same leave twice', function () {
    $leave = HrmLeave::factory()->approved()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->postJson("/api/v1/hrm-leaves/{$leave->id}/approve")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only a pending leave request can be reviewed.');
});

it('freezes a reviewed leave against edits', function () {
    $pending = HrmLeave::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);
    $approved = HrmLeave::factory()->approved()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->putJson("/api/v1/hrm-leaves/{$pending->id}", ['reason' => 'Still planning.'])
        ->assertOk()
        ->assertJsonPath('data.reason', 'Still planning.');

    $this->putJson("/api/v1/hrm-leaves/{$approved->id}", ['reason' => 'Never mind.'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A reviewed leave request cannot be edited.');
});

it('filters leaves by status and type', function () {
    HrmLeave::factory()->approved()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'leave_type' => 'sick',
    ]);
    HrmLeave::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'leave_type' => 'casual',
    ]);

    $this->getJson('/api/v1/hrm-leaves?status=approved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'approved');

    $this->getJson('/api/v1/hrm-leaves?leave_type=casual')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.leave_type', 'casual');
});

it('deletes and restores a leave', function () {
    $leave = HrmLeave::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->deleteJson("/api/v1/hrm-leaves/{$leave->id}")->assertNoContent();
    expect(HrmLeave::find($leave->id))->toBeNull();

    $this->postJson("/api/v1/hrm-leaves/{$leave->id}/restore")->assertOk();
    expect(HrmLeave::find($leave->id))->not->toBeNull();
});

it('blocks staff without the hrm-leaves permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "hrm-leaves.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $staff = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($staff);

    $this->getJson('/api/v1/hrm-leaves')->assertForbidden();
    $this->postJson('/api/v1/hrm-leaves', hrmLeavePayload([
        'employee_id' => $this->employee->id,
    ]))->assertForbidden();
});

it('gates the approve action behind the update permission', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "hrm-leaves.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $leave = HrmLeave::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->postJson("/api/v1/hrm-leaves/{$leave->id}/approve")->assertForbidden();

    $approver = User::factory()->create(['company_id' => $this->company->id]);
    $approver->givePermissionTo('hrm-leaves.update');
    Passport::actingAs($approver);

    $this->postJson("/api/v1/hrm-leaves/{$leave->id}/approve")->assertOk();
});
