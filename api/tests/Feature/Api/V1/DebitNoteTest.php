<?php

use App\Models\Company;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
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

function debitNote(array $overrides = []): DebitNote
{
    return DebitNote::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $overrides));
}

function debitNoteItem(DebitNote $note, array $overrides = []): DebitNoteItem
{
    return DebitNoteItem::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'debit_note_id' => $note->id,
    ], $overrides));
}

function grantDebitNotePermissions(User $user): void
{
    foreach (['debit-notes', 'debit-note-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'debit-note%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/debit-notes')->assertUnauthorized();
});

it('blocks users without the debit note permissions', function () {
    foreach (['debit-notes', 'debit-note-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/debit-notes')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantDebitNotePermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/debit-notes')->assertOk();

    $this->putJson('/api/v1/addons/debit-note', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/debit-notes')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'debit-note');
});

it('creates a debit note defaulting its status to draft', function () {
    $this->postJson('/api/v1/debit-notes', [
        'debit_note_number' => 'DN-1001',
        'reason' => 'Damaged goods supplied by the vendor',
    ])
        ->assertCreated()
        ->assertJsonPath('data.debit_note_number', 'DN-1001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('approves a draft debit note and refuses a second approval', function () {
    $note = debitNote();

    $response = $this->postJson("/api/v1/debit-notes/{$note->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
    expect($response->json('data.approved_at'))->not->toBeNull();

    $this->postJson("/api/v1/debit-notes/{$note->id}/approve")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a draft debit note can be approved.');
});

it('applies an approved debit note and refuses applying a draft', function () {
    $approved = debitNote([
        'status' => DebitNote::STATUS_APPROVED,
        'total' => 500,
        'applied_amount' => 0,
        'balance' => 500,
    ]);
    $draft = debitNote();

    $this->postJson("/api/v1/debit-notes/{$approved->id}/apply")
        ->assertOk()
        ->assertJsonPath('data.status', 'applied')
        ->assertJsonPath('data.applied_amount', 500)
        ->assertJsonPath('data.balance', 0);

    $this->postJson("/api/v1/debit-notes/{$draft->id}/apply")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only an approved debit note can be applied.');
});

it('recalculates the parent total when an item is added', function () {
    $note = debitNote();

    $this->postJson('/api/v1/debit-note-items', [
        'debit_note_id' => $note->id,
        'description' => 'Rework charges',
        'quantity' => 2,
        'unit_price' => 25,
        'total' => 50,
    ])->assertCreated();

    $fresh = $note->fresh();
    expect($fresh->total)->toBe(50.0);
    expect($fresh->balance)->toBe(50.0);
});

it('filters debit notes by status', function () {
    debitNote(['status' => DebitNote::STATUS_DRAFT]);
    debitNote(['status' => DebitNote::STATUS_APPROVED]);

    $this->getJson('/api/v1/debit-notes?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'draft');
});

it('scopes debit notes to the acting user’s company', function () {
    debitNote();
    debitNote(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/debit-notes')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
