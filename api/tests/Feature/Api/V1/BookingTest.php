<?php

use App\Models\Booking;
use App\Models\BookingResource;
use App\Models\Company;
use App\Models\Contact;
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

function bookableResource(): BookingResource
{
    return BookingResource::factory()->create(['company_id' => test()->company->id]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function bookingRecord(?BookingResource $resource = null, array $attributes = []): Booking
{
    return Booking::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'resource_id' => ($resource ?? bookableResource())->id,
    ], $attributes));
}

function grantBookingsPermissions(User $user): void
{
    foreach (['booking-resources', 'bookings'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'booking-resources.%')
        ->orWhere('name', 'like', 'bookings.%')
        ->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/bookings')->assertUnauthorized();
});

it('blocks users without the booking permissions', function () {
    grantBookingsPermissions($this->user);

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/bookings')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantBookingsPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/bookings')->assertOk();

    $this->putJson('/api/v1/addons/bookings', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/bookings')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'bookings');
});

it('creates a booking defaulting its status to pending', function () {
    $resource = bookableResource();

    $this->postJson('/api/v1/bookings', [
        'resource_id' => $resource->id,
        'title' => 'Board call',
        'customer_name' => 'Acme Corp',
        'starts_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Board call')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.resource.id', $resource->id)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects a booking whose window ends before it starts', function () {
    $this->postJson('/api/v1/bookings', [
        'title' => 'Board call',
        'starts_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_at']);
});

it('refuses an overlapping booking on the same resource', function () {
    $resource = bookableResource();
    bookingRecord($resource, [
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    $this->postJson('/api/v1/bookings', [
        'resource_id' => $resource->id,
        'title' => 'Overlap',
        'starts_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(12, 0)->toDateTimeString(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['resource_id']);
});

it('allows an adjacent window on the same resource', function () {
    $resource = bookableResource();
    bookingRecord($resource, [
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    $this->postJson('/api/v1/bookings', [
        'resource_id' => $resource->id,
        'title' => 'Back to back',
        'starts_at' => now()->addDay()->setTime(11, 0)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(12, 0)->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Back to back');
});

it('does not conflict across different resources', function () {
    $resource = bookableResource();
    bookingRecord($resource, [
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    $other = bookableResource();

    $this->postJson('/api/v1/bookings', [
        'resource_id' => $other->id,
        'title' => 'Different room',
        'starts_at' => now()->addDay()->setTime(9, 30)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(10, 30)->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.resource_id', $other->id);
});

it('moves a booking through confirm, check-in and complete', function () {
    $booking = bookingRecord();

    $this->postJson("/api/v1/bookings/{$booking->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $this->postJson("/api/v1/bookings/{$booking->id}/check-in")
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_in');

    $this->postJson("/api/v1/bookings/{$booking->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('refuses a pending booking jumping straight to check-in', function () {
    $booking = bookingRecord();

    $this->postJson("/api/v1/bookings/{$booking->id}/check-in")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('cancels a pending booking and refuses to reopen it', function () {
    $booking = bookingRecord();

    $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->postJson("/api/v1/bookings/{$booking->id}/confirm")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('marks a no-show booking and refuses to check it in', function () {
    $booking = bookingRecord();

    $this->postJson("/api/v1/bookings/{$booking->id}/no-show")
        ->assertOk()
        ->assertJsonPath('data.status', 'no_show');

    $this->postJson("/api/v1/bookings/{$booking->id}/check-in")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('refuses to edit a completed booking', function () {
    $booking = bookingRecord(null, ['status' => Booking::STATUS_COMPLETED]);

    $this->putJson("/api/v1/bookings/{$booking->id}", ['title' => 'Renamed'])
        ->assertStatus(422);
});

it('filters bookings by status', function () {
    bookingRecord();
    bookingRecord(null, ['status' => Booking::STATUS_CONFIRMED]);

    $this->getJson('/api/v1/bookings?status=confirmed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'confirmed');
});

it('searches bookings by title and customer', function () {
    bookingRecord(null, ['title' => 'Board call', 'customer_name' => 'Acme Corp']);
    bookingRecord(null, ['title' => 'Site walkthrough', 'customer_name' => 'Beta Ltd']);

    $this->getJson('/api/v1/bookings?search=Board')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/bookings?search=Beta')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('releases an overlap after a booking is cancelled', function () {
    $resource = bookableResource();
    $first = bookingRecord($resource, [
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    $this->postJson("/api/v1/bookings/{$first->id}/cancel")->assertOk();

    $this->postJson('/api/v1/bookings', [
        'resource_id' => $resource->id,
        'title' => 'Reuse slot',
        'starts_at' => now()->addDay()->setTime(9, 30)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(10, 30)->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Reuse slot');
});

it('scopes bookings to the acting userâ€™s company', function () {
    bookingRecord();
    bookingRecord(null, ['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('publishes a bookings summary feed', function () {
    $this->getJson('/api/v1/bookings/summary')
        ->assertOk()
        ->assertJsonStructure([
            'summary' => ['today_total', 'today_by_status', 'in_progress', 'upcoming', 'active_resources'],
        ]);

    $contact = Contact::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/bookings', [
        'title' => 'Finance review',
        'contact_id' => $contact->id,
        'customer_name' => 'Acme Corp',
        'starts_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
    ])->assertCreated();
});
