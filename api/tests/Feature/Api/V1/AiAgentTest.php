<?php

use App\Models\AiAgentMessage;
use App\Models\AiAgentThread;
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

function aiAgentThread(): AiAgentThread
{
    return AiAgentThread::factory()->create(['company_id' => test()->company->id]);
}

function grantAiAgentPermissions(User $user): void
{
    foreach (['ai-agent-threads', 'ai-agent-messages', 'ai-agent-prompts'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'ai-agent-%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/ai-agent-threads')->assertUnauthorized();
});

it('blocks users without the ai agent permissions', function () {
    foreach (['ai-agent-threads', 'ai-agent-messages', 'ai-agent-prompts'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/ai-agent-threads')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantAiAgentPermissions($this->user);
    Permission::firstOrCreate(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/ai-agent-threads')->assertOk();

    $this->putJson('/api/v1/addons/ai-agent', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/ai-agent-threads')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'ai-agent');
});

it('creates a thread', function () {
    $this->postJson('/api/v1/ai-agent-threads', ['title' => 'Quarterly planning'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Quarterly planning')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.user_id', $this->user->id);
});

it('sends a message creating a user and assistant turn and stamps the thread', function () {
    $thread = aiAgentThread();
    expect($thread->last_message_at)->toBeNull();

    $response = $this->postJson("/api/v1/ai-agent-threads/{$thread->id}/messages", [
        'body' => 'What is our cash position?',
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.role', AiAgentMessage::ROLE_USER)
        ->assertJsonPath('data.0.body', 'What is our cash position?')
        ->assertJsonPath('data.0.thread_id', $thread->id)
        ->assertJsonPath('data.1.role', AiAgentMessage::ROLE_ASSISTANT)
        ->assertJsonPath('data.1.thread_id', $thread->id);

    expect($response->json('data.1.body'))
        ->toBe('I received your request: What is our cash position?');

    expect(AiAgentMessage::where('thread_id', $thread->id)->count())->toBe(2);
    expect($thread->fresh()->last_message_at)->not->toBeNull();
});

it('lists a thread’s messages oldest first', function () {
    $thread = aiAgentThread();
    $this->postJson("/api/v1/ai-agent-threads/{$thread->id}/messages", ['body' => 'First']);
    $this->postJson("/api/v1/ai-agent-threads/{$thread->id}/messages", ['body' => 'Second']);

    $this->getJson("/api/v1/ai-agent-threads/{$thread->id}/messages")
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.body', 'First')
        ->assertJsonPath('data.1.role', AiAgentMessage::ROLE_ASSISTANT)
        ->assertJsonPath('data.2.body', 'Second');
});

it('runs prompt CRUD and restore', function () {
    grantAiAgentPermissions($this->user);

    $created = $this->postJson('/api/v1/ai-agent-prompts', [
        'label' => 'Summarise',
        'prompt' => 'Summarise the report in three bullets.',
        'category' => 'reports',
        'is_shared' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Summarise')
        ->assertJsonPath('data.is_shared', true)
        ->assertJsonPath('data.category', 'reports')
        ->assertJsonPath('data.company_id', $this->company->id);

    $id = $created->json('data.id');

    $this->getJson("/api/v1/ai-agent-prompts/{$id}")
        ->assertOk()
        ->assertJsonPath('data.label', 'Summarise');

    $this->putJson("/api/v1/ai-agent-prompts/{$id}", ['label' => 'Summarise v2'])
        ->assertOk()
        ->assertJsonPath('data.label', 'Summarise v2');

    $this->getJson('/api/v1/ai-agent-prompts')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->deleteJson("/api/v1/ai-agent-prompts/{$id}")->assertNoContent();

    $this->postJson("/api/v1/ai-agent-prompts/{$id}/restore")
        ->assertOk()
        ->assertJsonPath('data.label', 'Summarise v2');
});

it('scopes threads to the acting user’s company', function () {
    grantAiAgentPermissions($this->user);

    aiAgentThread();
    AiAgentThread::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/ai-agent-threads')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
