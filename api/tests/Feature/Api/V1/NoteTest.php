<?php

use App\Models\Company;
use App\Models\Note;
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

function noteRecord(): Note
{
    return Note::factory()->create([
        'company_id' => test()->company->id,
    ]);
}

function grantNotePermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "notes.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'notes%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/notes')->assertUnauthorized();
});

it('blocks users without the notes permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "notes.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/notes')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantNotePermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/notes')->assertOk();

    $this->putJson('/api/v1/addons/note', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/notes')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'note');
});

it('creates a note defaulting is_pinned to false', function () {
    $this->postJson('/api/v1/notes', [
        'title' => 'Groceries',
        'body' => 'Milk, eggs and bread',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Groceries')
        ->assertJsonPath('data.is_pinned', false)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects a missing title', function () {
    $this->postJson('/api/v1/notes', [
        'body' => 'No title here',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('updates a note to pin it', function () {
    $note = noteRecord();

    $this->patchJson("/api/v1/notes/{$note->id}", ['is_pinned' => true])
        ->assertOk()
        ->assertJsonPath('data.is_pinned', true);
});

it('filters notes by is_pinned', function () {
    noteRecord()->update(['is_pinned' => true]);
    noteRecord();

    $this->getJson('/api/v1/notes?is_pinned=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_pinned', true);
});

it('searches notes by body', function () {
    noteRecord()->update(['body' => 'Remember the milk']);
    noteRecord()->update(['body' => 'Call the bank']);

    $this->getJson('/api/v1/notes?search=milk')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('scopes notes to the acting user’s company', function () {
    noteRecord();
    noteRecord()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/notes')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
