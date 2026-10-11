<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\ProposalItem;
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
function proposal(array $attributes = []): Proposal
{
    return Proposal::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'status' => Proposal::STATUS_DRAFT,
    ], $attributes));
}

function grantProposalPermissions(User $user): void
{
    foreach (['proposals', 'proposal-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'proposal%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/proposals')->assertUnauthorized();
});

it('blocks users without the proposal permissions', function () {
    foreach (['proposals', 'proposal-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/proposals')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantProposalPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/proposals')->assertOk();

    $this->putJson('/api/v1/addons/proposal', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/proposals')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'proposal');
});

it('creates a draft proposal', function () {
    $this->postJson('/api/v1/proposals', [
        'title' => 'Website redesign',
        'currency' => 'USD',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Website redesign')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('sends a draft proposal and refuses to send anything else', function () {
    $proposal = proposal();

    $this->postJson("/api/v1/proposals/{$proposal->id}/send")
        ->assertOk()
        ->assertJsonPath('data.status', 'sent');

    $this->postJson("/api/v1/proposals/{$proposal->id}/send")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a draft proposal can be sent.');
});

it('accepts a sent proposal', function () {
    $proposal = proposal(['status' => Proposal::STATUS_SENT]);

    $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');
});

it('rejects a sent proposal', function () {
    $proposal = proposal(['status' => Proposal::STATUS_SENT]);

    $this->postJson("/api/v1/proposals/{$proposal->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

it('converts an accepted proposal into an invoice with its items', function () {
    $contact = Contact::factory()->create(['company_id' => $this->company->id]);

    $proposal = proposal([
        'contact_id' => $contact->id,
        'status' => Proposal::STATUS_ACCEPTED,
        'subtotal' => 200,
        'tax_amount' => 20,
        'discount_amount' => 0,
        'total' => 220,
    ]);

    ProposalItem::factory()->create([
        'company_id' => $this->company->id,
        'proposal_id' => $proposal->id,
        'description' => 'Design work',
        'quantity' => 2,
        'unit_price' => 100,
        'total' => 200,
    ]);

    $this->postJson("/api/v1/proposals/{$proposal->id}/convert")
        ->assertOk()
        ->assertJsonPath('data.status', 'converted')
        ->assertJsonStructure(['data' => ['invoice_id', 'invoice' => ['id', 'items']]]);

    $proposal->refresh();

    expect($proposal->invoice_id)->not->toBeNull()
        ->and($proposal->status)->toBe(Proposal::STATUS_CONVERTED);

    $invoice = Invoice::findOrFail($proposal->invoice_id);

    expect($invoice->items()->count())->toBe(1)
        ->and($invoice->contact_id)->toBe($contact->id)
        ->and($invoice->type)->toBe('invoice');
});

it('refuses to convert a proposal that is not accepted', function () {
    $proposal = proposal();

    $this->postJson("/api/v1/proposals/{$proposal->id}/convert")->assertStatus(422);
});

it('filters proposals by status', function () {
    proposal();
    proposal(['status' => Proposal::STATUS_SENT]);

    $this->getJson('/api/v1/proposals?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'draft');
});

it('scopes proposals to the acting user’s company', function () {
    proposal();
    proposal(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/proposals')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
