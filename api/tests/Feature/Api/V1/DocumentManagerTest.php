<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentFolder;
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

function documentFolder(?int $parentId = null): DocumentFolder
{
    return DocumentFolder::factory()->create([
        'company_id' => test()->company->id,
        'parent_id' => $parentId,
    ]);
}

function documentFile(?DocumentFolder $folder = null): Document
{
    return Document::factory()->create([
        'company_id' => test()->company->id,
        'folder_id' => $folder?->id,
    ]);
}

function grantDocumentPermissions(User $user): void
{
    foreach (['document-folders', 'documents'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'document%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/documents')->assertUnauthorized();
    $this->getJson('/api/v1/document-folders')->assertUnauthorized();
});

it('blocks users without the document-manager permissions', function () {
    foreach (['document-folders', 'documents'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/documents')->assertForbidden();
    $this->getJson('/api/v1/document-folders')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantDocumentPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/documents')->assertOk();

    $this->putJson('/api/v1/addons/document-manager', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/documents')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'document-manager');
});

it('creates a folder', function () {
    $this->postJson('/api/v1/document-folders', ['name' => 'Contracts'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Contracts')
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('creates a nested folder with a parent', function () {
    $parent = documentFolder();

    $this->postJson('/api/v1/document-folders', [
        'parent_id' => $parent->id,
        'name' => '2026',
    ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);
});

it('refuses to delete a folder that still has children or documents', function () {
    $parent = documentFolder();
    documentFolder($parent->id);

    $this->deleteJson("/api/v1/document-folders/{$parent->id}")->assertStatus(422);

    $occupied = documentFolder();
    documentFile($occupied);

    $this->deleteJson("/api/v1/document-folders/{$occupied->id}")->assertStatus(422);

    $empty = documentFolder();

    $this->deleteJson("/api/v1/document-folders/{$empty->id}")->assertNoContent();
});

it('creates a document with default type, version and size', function () {
    $this->postJson('/api/v1/documents', ['name' => 'README.md'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'README.md')
        ->assertJsonPath('data.type', 'file')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.size', 0)
        ->assertJsonPath('data.is_public', false)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('bumps the version when content or url changes', function () {
    $document = documentFile();

    $this->putJson("/api/v1/documents/{$document->id}", ['content' => 'v2 body'])
        ->assertOk()
        ->assertJsonPath('data.version', 2);

    $this->putJson("/api/v1/documents/{$document->id}", [
        'url' => 'https://example.com/v3.pdf',
    ])
        ->assertOk()
        ->assertJsonPath('data.version', 3);

    $this->putJson("/api/v1/documents/{$document->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.version', 3);
});

it('shares a document with other users', function () {
    $document = documentFile();
    $collaborator = User::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/documents/{$document->id}/share", [
        'user_ids' => [$collaborator->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.shared_with', [$collaborator->id]);

    $this->postJson("/api/v1/documents/{$document->id}/share", [
        'user_ids' => [$collaborator->id, $this->user->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.shared_with', [$collaborator->id, $this->user->id]);
});

it('validates that shared users exist', function () {
    $document = documentFile();

    $this->postJson("/api/v1/documents/{$document->id}/share", ['user_ids' => [999999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids.0']);
});

it('scopes folders and documents to the acting user’s company', function () {
    documentFolder();
    documentFolder()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/document-folders')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    documentFile();
    documentFile()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
