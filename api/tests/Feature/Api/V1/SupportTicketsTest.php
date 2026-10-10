<?php

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketReply;
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

function ticket(?string $state = null): Ticket
{
    $factory = Ticket::factory();

    if ($state !== null) {
        $factory = $factory->{$state}();
    }

    return $factory->create(['company_id' => test()->company->id]);
}

function grantTicketPermissions(User $user): void
{
    foreach (['tickets', 'ticket-replies'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(
        Permission::where('name', 'like', 'tickets.%')
            ->orWhere('name', 'like', 'ticket-replies.%')
            ->get()
    );
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/tickets')->assertUnauthorized();
    $this->getJson('/api/v1/ticket-replies')->assertUnauthorized();
});

it('blocks users without the support ticket permissions', function () {
    foreach (['tickets', 'ticket-replies'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/tickets')->assertForbidden();
    $this->getJson('/api/v1/ticket-replies')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantTicketPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/tickets')->assertOk();

    $this->putJson('/api/v1/addons/support-tickets', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/tickets')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'support-tickets');
});

it('creates a ticket with a generated number and open defaults', function () {
    grantTicketPermissions($this->user);

    $this->postJson('/api/v1/tickets', [
        'subject' => 'The printer on the second floor is jammed',
        'description' => 'It refuses to pick up paper.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'The printer on the second floor is jammed')
        ->assertJsonPath('data.ticket_number', 'TKT-'.date('y').'-0001')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.priority', 'medium')
        ->assertJsonPath('data.resolved_at', null)
        ->assertJsonPath('data.closed_at', null)
        ->assertJsonPath('data.company_id', $this->company->id);

    $this->postJson('/api/v1/tickets', [
        'subject' => 'Login is slow this morning',
    ])
        ->assertCreated()
        ->assertJsonPath('data.ticket_number', 'TKT-'.date('y').'-0002');
});

it('rejects an invalid priority', function () {
    grantTicketPermissions($this->user);

    $this->postJson('/api/v1/tickets', [
        'subject' => 'A torn curtain',
        'priority' => 'whenever',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});

it('resolves a ticket and stamps resolved_at', function () {
    $ticket = ticket();

    $this->postJson("/api/v1/tickets/{$ticket->id}/resolve")
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolved_at', fn ($value) => $value !== null);
});

it('refuses to resolve an already closed ticket', function () {
    $ticket = ticket('closed');

    $this->postJson("/api/v1/tickets/{$ticket->id}/resolve")->assertStatus(422);
});

it('closes then reopens a ticket back to open and clears the timestamps', function () {
    $ticket = ticket('resolved');

    $this->postJson("/api/v1/tickets/{$ticket->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closed_at', fn ($value) => $value !== null);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reopen")
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.resolved_at', null)
        ->assertJsonPath('data.closed_at', null);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reopen")->assertStatus(422);
});

it('assigns a ticket to a staff member but never a closed one', function () {
    $ticket = ticket();
    $agent = User::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['assigned_to' => $agent->id])
        ->assertOk()
        ->assertJsonPath('data.assigned_to', $agent->id);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_to']);

    $closed = ticket('closed');

    $this->postJson("/api/v1/tickets/{$closed->id}/assign", ['assigned_to' => $agent->id])
        ->assertStatus(422);
});

it('refuses a reply on a closed ticket', function () {
    $closed = ticket('closed');

    $this->postJson('/api/v1/ticket-replies', [
        'ticket_id' => $closed->id,
        'body' => 'Could you look at this again?',
    ])
        ->assertStatus(422);

    $open = ticket();

    $this->postJson('/api/v1/ticket-replies', [
        'ticket_id' => $open->id,
        'body' => 'Could you look at this again?',
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', false);
});

it('stores an internal reply flagged as internal', function () {
    $ticket = ticket();

    $this->postJson('/api/v1/ticket-replies', [
        'ticket_id' => $ticket->id,
        'body' => 'Waiting on the supplier for a replacement part.',
        'is_internal' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', true)
        ->assertJsonPath('data.ticket_id', $ticket->id)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(TicketReply::count())->toBe(1);
});

it('scopes tickets to the acting user’s company', function () {
    ticket();
    ticket()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters and searches the ticket list', function () {
    $open = ticket();
    $closed = ticket('closed');

    $this->getJson('/api/v1/tickets?status=open')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->id);

    $this->getJson('/api/v1/tickets?status=closed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $closed->id);

    $this->getJson('/api/v1/tickets?search='.urlencode($open->ticket_number))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->id);
});
