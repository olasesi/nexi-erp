<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendee;
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

function calendarEvent(): Event
{
    return Event::factory()->create(['company_id' => test()->company->id]);
}

function grantEventPermissions(User $user): void
{
    foreach (['events', 'event-attendees'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(
        Permission::where('name', 'like', 'events.%')
            ->orWhere('name', 'like', 'event-attendees.%')
            ->get()
    );
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/events')->assertUnauthorized();
});

it('blocks users without the calendar permissions', function () {
    foreach (['events', 'event-attendees'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/events')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantEventPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/events')->assertOk();

    $this->putJson('/api/v1/addons/calendar-events', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/events')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'calendar-events');
});

it('creates an event with a default scheduled status', function () {
    $this->postJson('/api/v1/events', [
        'title' => 'Quarterly Planning',
        'starts_at' => now()->addWeek()->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Quarterly Planning')
        ->assertJsonPath('data.type', Event::TYPE_MEETING)
        ->assertJsonPath('data.status', Event::STATUS_SCHEDULED)
        ->assertJsonPath('data.all_day', false)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects an event whose end is before its start', function () {
    $this->postJson('/api/v1/events', [
        'title' => 'Mishandled',
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->toDateTimeString(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_at']);
});

it('filters events by a date range', function () {
    Event::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Older',
        'starts_at' => now()->subWeek()->toDateTimeString(),
    ]);
    Event::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Fresh',
        'starts_at' => now()->addWeek()->toDateTimeString(),
    ]);

    $this->getJson('/api/v1/events?from='.now()->addDay()->toDateString().'&to='.now()->addMonth()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Fresh');
});

it('cancels an event and refuses to cancel it twice', function () {
    $event = calendarEvent();

    $this->postJson("/api/v1/events/{$event->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', Event::STATUS_CANCELLED);

    expect($event->fresh()->status)->toBe(Event::STATUS_CANCELLED);

    $this->postJson("/api/v1/events/{$event->id}/cancel")->assertStatus(422);
});

it('adds an attendee to an event', function () {
    $event = calendarEvent();

    $this->postJson('/api/v1/event-attendees', [
        'event_id' => $event->id,
        'email' => 'guest@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('data.event_id', $event->id)
        ->assertJsonPath('data.email', 'guest@example.com')
        ->assertJsonPath('data.status', EventAttendee::STATUS_INVITED)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('requires at least one attendee reference', function () {
    $event = calendarEvent();

    $this->postJson('/api/v1/event-attendees', ['event_id' => $event->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id', 'contact_id', 'email']);
});

it('records an attendee response and blocks responses to cancelled events', function () {
    $attendee = EventAttendee::factory()->create([
        'company_id' => $this->company->id,
        'event_id' => calendarEvent()->id,
        'email' => 'guest@example.com',
    ]);

    $this->postJson("/api/v1/event-attendees/{$attendee->id}/respond", ['status' => EventAttendee::STATUS_ACCEPTED])
        ->assertOk()
        ->assertJsonPath('data.status', EventAttendee::STATUS_ACCEPTED);

    expect($attendee->fresh()->status)->toBe(EventAttendee::STATUS_ACCEPTED);

    $cancelledEvent = calendarEvent();
    $cancelledEvent->update(['status' => Event::STATUS_CANCELLED]);
    $cancelledAttendee = EventAttendee::factory()->create([
        'company_id' => $this->company->id,
        'event_id' => $cancelledEvent->id,
        'email' => 'guest@example.com',
    ]);

    $this->postJson("/api/v1/event-attendees/{$cancelledAttendee->id}/respond", ['status' => EventAttendee::STATUS_ACCEPTED])
        ->assertStatus(422);
});

it('scopes events to the acting user’s company', function () {
    calendarEvent();
    Event::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
