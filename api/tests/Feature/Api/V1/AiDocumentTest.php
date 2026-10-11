<?php

use App\Models\AiDocument;
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

function aiDocumentRecord(): AiDocument
{
    return AiDocument::factory()->create(['company_id' => test()->company->id]);
}

function grantAiDocumentPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "ai-documents.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'ai-documents.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/ai-documents')->assertUnauthorized();
});

it('blocks users without the ai document permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "ai-documents.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/ai-documents')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantAiDocumentPermissions($this->user);
    Permission::firstOrCreate(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/ai-documents')->assertOk();

    $this->putJson('/api/v1/addons/ai-document', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/ai-documents')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'ai-document');
});

it('creates a pending document owned by the acting user', function () {
    grantAiDocumentPermissions($this->user);

    $this->postJson('/api/v1/ai-documents', [
        'title' => 'Refund policy',
        'prompt' => 'Draft a customer-friendly refund policy.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Refund policy')
        ->assertJsonPath('data.status', AiDocument::STATUS_PENDING)
        ->assertJsonPath('data.language', 'en')
        ->assertJsonPath('data.content', null)
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.user_id', $this->user->id);
});

it('generates a document through the configured driver', function () {
    grantAiDocumentPermissions($this->user);

    $document = aiDocumentRecord();

    $response = $this->postJson("/api/v1/ai-documents/{$document->id}/generate")
        ->assertOk()
        ->assertJsonPath('data.status', AiDocument::STATUS_GENERATED)
        ->assertJsonPath('data.provider', 'stub');

    expect($response->json('data.content'))->toContain($document->prompt);
    expect($response->json('data.tokens'))->toBeInt();
    expect($response->json('data.generated_at'))->not->toBeNull();
    expect($document->fresh()->generated_at)->not->toBeNull();
});

it('filters and searches documents', function () {
    grantAiDocumentPermissions($this->user);

    AiDocument::factory()->create(['company_id' => $this->company->id, 'title' => 'Alpha memo', 'status' => AiDocument::STATUS_PENDING]);
    AiDocument::factory()->generated()->create(['company_id' => $this->company->id, 'title' => 'Beta memo']);

    $this->getJson('/api/v1/ai-documents?status=generated')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Beta memo');

    $this->getJson('/api/v1/ai-documents?search=Alpha')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Alpha memo');
});

it('updates, deletes and restores a document', function () {
    grantAiDocumentPermissions($this->user);

    $document = aiDocumentRecord();

    $this->putJson("/api/v1/ai-documents/{$document->id}", ['title' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Renamed');

    $this->deleteJson("/api/v1/ai-documents/{$document->id}")->assertNoContent();

    $this->postJson("/api/v1/ai-documents/{$document->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.title', 'Renamed');
});

it('scopes documents to the acting user’s company', function () {
    grantAiDocumentPermissions($this->user);

    aiDocumentRecord();
    AiDocument::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/ai-documents')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
