<?php

use App\Models\Company;
use App\Models\CoworkingBooking;
use App\Models\CoworkingUnit;
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
});

function coworkingUnitPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Desk 12',
        'type' => 'desk',
        'capacity' => 1,
        'rate_hourly' => 10,
        'rate_daily' => 100,
    ], $overrides);
}

function coworkingBookingPayload(int $unitId, array $overrides = []): array
{
    return array_merge([
        'unit_id' => $unitId,
        'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
        'end_at' => now()->addDays(2)->setTime(12, 0)->toDateTimeString(),
        'seats' => 1,
    ], $overrides);
}

function grantCoworkingPermissions(User $user): void
{
    foreach (['coworking-units', 'coworking-bookings'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    $user->givePermissionTo(
        Permission::where('name', 'like', 'coworking-%')->get()
    );
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/coworking-units')->assertUnauthorized();
    $this->getJson('/api/v1/coworking-bookings')->assertUnauthorized();
});

it('blocks users without the coworking permissions', function () {
    foreach (['coworking-units', 'coworking-bookings'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/coworking-units')->assertForbidden();
    $this->postJson('/api/v1/coworking-bookings', coworkingBookingPayload(1))->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantCoworkingPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/coworking-units')->assertOk();

    $this->putJson('/api/v1/addons/coworking-space', ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);

    $this->getJson('/api/v1/coworking-units')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'coworking-space');
});

it('lists only the acting user’s company units', function () {
    CoworkingUnit::factory()->create(['company_id' => $this->company->id, 'name' => 'Meeting Room A']);
    CoworkingUnit::factory()->create(['company_id' => $this->company->id, 'name' => 'Desk 1']);
    CoworkingUnit::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/coworking-units')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('creates a unit stamped with the acting user’s company', function () {
    $this->postJson('/api/v1/coworking-units', coworkingUnitPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Desk 12')
        ->assertJsonPath('data.type', 'desk')
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(CoworkingUnit::count())->toBe(1);
});

it('validates the unit payload', function () {
    $this->postJson('/api/v1/coworking-units', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'type', 'capacity']);

    $this->postJson('/api/v1/coworking-units', coworkingUnitPayload(['type' => 'yacht']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('updates a unit', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);

    $this->putJson("/api/v1/coworking-units/{$unit->id}", ['name' => 'Quiet Booth', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'Quiet Booth')
        ->assertJsonPath('data.is_active', false);
});

it('shows availability with conflicts and capacity, scoped to the company', function () {
    $unit = CoworkingUnit::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Booth 1',
        'capacity' => 1,
    ]);
    CoworkingUnit::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Booth 2',
        'capacity' => 1,
    ]);
    $other = CoworkingUnit::factory()->create(['company_id' => Company::factory()->create()->id]);

    CoworkingBooking::factory()->confirmed()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->addDays(2)->setTime(9, 0),
        'end_at' => now()->addDays(2)->setTime(12, 0),
    ]);
    CoworkingBooking::factory()->confirmed()->create([
        'company_id' => Company::factory()->create()->id,
        'unit_id' => $other->id,
    ]);

    $response = $this->getJson('/api/v1/coworking-units/availability?start_at='
        .now()->addDays(2)->setTime(9, 0)->toDateTimeString()
        .'&end_at='.now()->addDays(2)->setTime(12, 0)->toDateTimeString())
        ->assertOk();

    $rows = collect($response->json('data'))->keyBy('name');

    expect($response->json('data'))->toHaveCount(2)
        ->and($rows['Booth 1']['available'])->toBeFalse()
        ->and($rows['Booth 1']['conflicting_bookings'])->toBe(1)
        ->and($rows['Booth 2']['available'])->toBeTrue()
        ->and($rows['Booth 2']['conflicting_bookings'])->toBe(0);

    // A party of two still cannot use a single-seat booth.
    $this->getJson('/api/v1/coworking-units/availability?start_at='
        .now()->addDays(3)->toDateTimeString()
        .'&end_at='.now()->addDays(3)->addHours(3)->toDateTimeString()
        .'&seats=2')
        ->assertOk()
        ->assertJsonPath('data.0.available', false)
        ->assertJsonPath('data.1.available', false);
});

it('creates a booking with a computed price and default status', function () {
    $unit = CoworkingUnit::factory()->create([
        'company_id' => $this->company->id,
        'rate_hourly' => 10,
        'rate_daily' => 100,
    ]);

    $this->postJson('/api/v1/coworking-bookings', coworkingBookingPayload($unit->id))
        ->assertCreated()
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.price', 30)
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id);

    expect(CoworkingBooking::count())->toBe(1);
});

it('prices multi-day bookings from the daily rate', function () {
    $unit = CoworkingUnit::factory()->create([
        'company_id' => $this->company->id,
        'rate_hourly' => 10,
        'rate_daily' => 100,
    ]);

    $start = now()->addDays(5)->setTime(8, 0);
    $end = $start->copy()->addDays(2)->setTime(18, 0);

    $this->postJson('/api/v1/coworking-bookings', coworkingBookingPayload($unit->id, [
        'start_at' => $start->toDateTimeString(),
        'end_at' => $end->toDateTimeString(),
    ]))
        ->assertCreated()
        ->assertJsonPath('data.price', 300);
});

it('rejects a booking that exceeds the unit capacity', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id, 'capacity' => 1]);

    $this->postJson('/api/v1/coworking-bookings', coworkingBookingPayload($unit->id, ['seats' => 3]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['seats']);
});

it('rejects double-bookings of the same slot', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);

    CoworkingBooking::factory()->confirmed()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->addDays(2)->setTime(9, 0),
        'end_at' => now()->addDays(2)->setTime(12, 0),
    ]);

    $this->postJson('/api/v1/coworking-bookings', coworkingBookingPayload($unit->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

it('lets a cancelled booking free the slot', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);
    $existing = CoworkingBooking::factory()->cancelled()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->addDays(2)->setTime(9, 0),
        'end_at' => now()->addDays(2)->setTime(12, 0),
    ]);

    $this->postJson('/api/v1/coworking-bookings', coworkingBookingPayload($unit->id))
        ->assertCreated();

    expect(CoworkingBooking::count())->toBe(2)
        ->and($existing->fresh()->status)->toBe('cancelled');
});

it('advances a booking through confirm, check-in and check-out', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);
    $booking = CoworkingBooking::factory()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->addDay()->setTime(10, 0),
        'end_at' => now()->addDay()->setTime(12, 0),
    ]);

    $this->postJson("/api/v1/coworking-bookings/{$booking->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $this->postJson("/api/v1/coworking-bookings/{$booking->id}/check-in")
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_in');

    $this->postJson("/api/v1/coworking-bookings/{$booking->id}/check-out")
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_out');
});

it('refuses illegal lifecycle jumps', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);
    $requested = CoworkingBooking::factory()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(3),
    ]);

    $this->postJson("/api/v1/coworking-bookings/{$requested->id}/check-in")
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_in');

    // Already checked out: cannot be reopened.
    $this->postJson("/api/v1/coworking-bookings/{$requested->id}/cancel")->assertStatus(422);

    $cancelled = CoworkingBooking::factory()->cancelled()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
    ]);

    $this->postJson("/api/v1/coworking-bookings/{$cancelled->id}/confirm")->assertStatus(422);
});

it('refuses to check in a booking whose window already passed', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);
    $booking = CoworkingBooking::factory()->confirmed()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->subDays(2)->setTime(9, 0),
        'end_at' => now()->subDays(2)->setTime(12, 0),
    ]);

    $this->postJson("/api/v1/coworking-bookings/{$booking->id}/check-in")
        ->assertStatus(422);
});

it('recomputes the price when a booking is rescheduled', function () {
    $unit = CoworkingUnit::factory()->create([
        'company_id' => $this->company->id,
        'rate_hourly' => 10,
        'rate_daily' => 100,
    ]);
    $booking = CoworkingBooking::factory()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->addDays(2)->setTime(9, 0),
        'end_at' => now()->addDays(2)->setTime(12, 0),
        'price' => 30,
    ]);

    $this->putJson("/api/v1/coworking-bookings/{$booking->id}", [
        'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
        'end_at' => now()->addDays(2)->setTime(14, 0)->toDateTimeString(),
    ])
        ->assertOk()
        ->assertJsonPath('data.price', 50);
});

it('rejects an edit that creates an overlap and blocks finished edits', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);

    CoworkingBooking::factory()->confirmed()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->addDays(2)->setTime(9, 0),
        'end_at' => now()->addDays(2)->setTime(12, 0),
    ]);

    $movable = CoworkingBooking::factory()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->addDays(3)->setTime(9, 0),
        'end_at' => now()->addDays(3)->setTime(12, 0),
    ]);

    $this->putJson("/api/v1/coworking-bookings/{$movable->id}", [
        'start_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'end_at' => now()->addDays(2)->setTime(13, 0)->toDateTimeString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['unit_id']);

    $finished = CoworkingBooking::factory()->checkedOut()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
    ]);

    $this->putJson("/api/v1/coworking-bookings/{$finished->id}", ['notes' => 'nope'])
        ->assertStatus(422);
});

it('only deletes bookings that have not started', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);
    $requested = CoworkingBooking::factory()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
    ]);
    $checkedOut = CoworkingBooking::factory()->checkedOut()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
    ]);

    $this->deleteJson("/api/v1/coworking-bookings/{$requested->id}")->assertNoContent();
    $this->deleteJson("/api/v1/coworking-bookings/{$checkedOut->id}")->assertStatus(422);
});

it('summarises the day’s bookings and live occupancy', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
    CoworkingUnit::factory()->create(['company_id' => $this->company->id, 'is_active' => false]);

    CoworkingBooking::factory()->confirmed()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->setTime(10, 0),
        'end_at' => now()->setTime(12, 0),
    ]);
    CoworkingBooking::factory()->checkedIn()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
        'start_at' => now()->setTime(14, 0),
        'end_at' => now()->addHours(2),
    ]);

    $this->getJson('/api/v1/coworking-bookings/summary')
        ->assertOk()
        ->assertJsonPath('summary.today_total', 2)
        ->assertJsonPath('summary.today_by_status.confirmed', 1)
        ->assertJsonPath('summary.today_by_status.checked_in', 1)
        ->assertJsonPath('summary.in_progress', 1)
        ->assertJsonPath('summary.checked_in_units', 1)
        ->assertJsonPath('summary.active_units', 1);
});

it('scopes bookings to the acting user’s company', function () {
    $unit = CoworkingUnit::factory()->create(['company_id' => $this->company->id]);

    CoworkingBooking::factory()->create(['company_id' => $this->company->id, 'unit_id' => $unit->id]);
    CoworkingBooking::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/coworking-bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
