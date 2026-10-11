<?php

use App\Models\BookingResource;
use App\Models\Company;
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

function bookingResourceRecord(): BookingResource
{
    return BookingResource::factory()->create(['company_id' => test()->company->id]);
}

function grantBookingPermissions(User $user): void
{
    foreach (['booking-resources', 'bookings'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'booking-resources.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/booking-resources')->assertUnauthorized();
});

it('blocks users without the booking resource permissions', function () {
    foreach (['booking-resources', 'bookings'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/booking-resources')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantBookingPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/booking-resources')->assertOk();

    $this->putJson('/api/v1/addons/bookings', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/booking-resources')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'bookings');
});

it('creates a resource defaulting its type and active flag', function () {
    $this->postJson('/api/v1/booking-resources', [
        'name' => 'Conference Room A',
        'capacity' => 8,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Conference Room A')
        ->assertJsonPath('data.type', 'room')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.capacity', 8)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects an invalid resource type', function () {
    $this->postJson('/api/v1/booking-resources', [
        'name' => 'Mystery Room',
        'type' => 'pod',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('filters resources by type', function () {
    bookingResourceRecord()->update(['type' => 'room']);
    bookingResourceRecord()->update(['type' => 'vehicle']);

    $this->getJson('/api/v1/booking-resources?type=room')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'room');
});

it('searches resources by name', function () {
    bookingResourceRecord()->update(['name' => 'Projector Kit']);
    bookingResourceRecord()->update(['name' => 'Lectern Kit']);

    $this->getJson('/api/v1/booking-resources?search=Projector')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Projector Kit');
});

it('scopes resources to the acting user’s company', function () {
    bookingResourceRecord();
    bookingResourceRecord()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/booking-resources')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('restores a soft-deleted resource', function () {
    $resource = bookingResourceRecord();
    $this->deleteJson("/api/v1/booking-resources/{$resource->id}")->assertNoContent();

    $this->postJson("/api/v1/booking-resources/{$resource->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.name', $resource->name);
});
