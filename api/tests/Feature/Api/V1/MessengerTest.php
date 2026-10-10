<?php

use App\Models\Company;
use App\Models\Conversation;
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

function messengerConversation(): Conversation
{
    return Conversation::factory()->create(['company_id' => test()->company->id]);
}

function grantMessengerPermissions(User $user): void
{
    foreach (['conversations', 'messages'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    $user->givePermissionTo(
        Permission::where('name', 'like', 'conversations.%')
            ->orWhere('name', 'like', 'messages.%')
            ->get()
    );
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/conversations')->assertUnauthorized();
});

it('blocks users without the messenger permissions', function () {
    foreach (['conversations', 'messages'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/conversations')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantMessengerPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/conversations')->assertOk();

    $this->putJson('/api/v1/addons/messenger', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/conversations')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'messenger');
});

it('creates a conversation with default status open', function () {
    $this->postJson('/api/v1/conversations', ['subject' => 'Hello there'])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'Hello there')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.is_group', false)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('closes then reopens a conversation back to open', function () {
    $conversation = messengerConversation();

    $this->postJson("/api/v1/conversations/{$conversation->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    $this->postJson("/api/v1/conversations/{$conversation->id}/reopen")
        ->assertOk()
        ->assertJsonPath('data.status', 'open');
});

it('refuses to reopen an archived conversation', function () {
    $conversation = messengerConversation();
    $conversation->update(['status' => Conversation::STATUS_ARCHIVED]);

    $this->postJson("/api/v1/conversations/{$conversation->id}/reopen")->assertStatus(422);
});

it('storing a message updates last_message_at and defaults direction to out', function () {
    $conversation = messengerConversation();
    expect($conversation->last_message_at)->toBeNull();

    $this->postJson('/api/v1/messages', [
        'conversation_id' => $conversation->id,
        'body' => 'Hi there',
    ])
        ->assertCreated()
        ->assertJsonPath('data.direction', 'out')
        ->assertJsonPath('data.read_at', fn ($value) => $value !== null)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect($conversation->fresh()->last_message_at)->not->toBeNull();
});

it('leaves read_at null for an incoming message', function () {
    $conversation = messengerConversation();

    $this->postJson('/api/v1/messages', [
        'conversation_id' => $conversation->id,
        'body' => 'Incoming',
        'direction' => 'in',
    ])
        ->assertCreated()
        ->assertJsonPath('data.direction', 'in')
        ->assertJsonPath('data.read_at', null);
});

it('requires a body for a message', function () {
    $conversation = messengerConversation();

    $this->postJson('/api/v1/messages', ['conversation_id' => $conversation->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('scopes conversations to the acting user’s company', function () {
    messengerConversation();
    Conversation::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
