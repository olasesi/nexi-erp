<?php

use App\Models\Company;
use App\Models\CreditNote;
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

/**
 * @param  array<string, mixed>  $attributes
 */
function creditNote(array $attributes = []): CreditNote
{
    return CreditNote::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $attributes));
}

function grantCreditNotePermissions(User $user): void
{
    foreach (['credit-notes', 'credit-note-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'credit-note%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/credit-notes')->assertUnauthorized();
});

it('blocks users without the credit note permissions', function () {
    foreach (['credit-notes', 'credit-note-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/credit-notes')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantCreditNotePermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/credit-notes')->assertOk();

    $this->putJson('/api/v1/addons/credit-note', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/credit-notes')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'credit-note');
});

it('creates a draft credit note', function () {
    $this->postJson('/api/v1/credit-notes', [
        'credit_note_number' => 'CN-0001',
        'reason' => 'Damaged goods',
    ])
        ->assertCreated()
        ->assertJsonPath('data.credit_note_number', 'CN-0001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id);
});

it('approves a draft once and refuses a second approval', function () {
    $note = creditNote();

    $this->postJson("/api/v1/credit-notes/{$note->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($note->fresh()->approved_at)->not->toBeNull();

    $this->postJson("/api/v1/credit-notes/{$note->id}/approve")->assertStatus(422);
});

it('applies an approved credit note and refuses a draft', function () {
    $approved = creditNote([
        'status' => CreditNote::STATUS_APPROVED,
        'total' => 150,
        'balance' => 150,
    ]);

    $this->postJson("/api/v1/credit-notes/{$approved->id}/apply")
        ->assertOk()
        ->assertJsonPath('data.status', 'applied')
        ->assertJsonPath('data.balance', 0);

    $approved->refresh();

    expect((float) $approved->applied_amount)->toBe(150.0)
        ->and((float) $approved->balance)->toBe(0.0);

    $draft = creditNote();

    $this->postJson("/api/v1/credit-notes/{$draft->id}/apply")->assertStatus(422);
});

it('recalculates the credit note total when an item is added', function () {
    $note = creditNote();

    $this->postJson('/api/v1/credit-note-items', [
        'credit_note_id' => $note->id,
        'description' => 'Refund line',
        'quantity' => 2,
        'unit_price' => 40,
        'total' => 80,
    ])->assertCreated();

    $note->refresh();

    expect((float) $note->subtotal)->toBe(80.0)
        ->and((float) $note->total)->toBe(80.0)
        ->and((float) $note->balance)->toBe(80.0);
});

it('filters credit notes by status', function () {
    creditNote(['status' => CreditNote::STATUS_DRAFT]);
    creditNote(['status' => CreditNote::STATUS_APPROVED]);

    $this->getJson('/api/v1/credit-notes?status=approved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'approved');
});

it('scopes credit notes to the acting user’s company', function () {
    creditNote();
    creditNote(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/credit-notes')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
