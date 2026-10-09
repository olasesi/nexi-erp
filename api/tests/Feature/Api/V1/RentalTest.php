<?php

use App\Models\Company;
use App\Models\RentalAgreement;
use App\Models\RentalItem;
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

function rentalAgreement(): RentalAgreement
{
    return RentalAgreement::factory()->create(['company_id' => test()->company->id, 'contact_id' => null]);
}

function rentalItem(RentalAgreement $agreement): RentalItem
{
    return RentalItem::factory()->create([
        'company_id' => test()->company->id,
        'rental_agreement_id' => $agreement->id,
        'product_id' => null,
    ]);
}

function grantRentalPermissions(User $user): void
{
    foreach (['rental-agreements', 'rental-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'rental-%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/rental-agreements')->assertUnauthorized();
});

it('blocks users without the rental permissions', function () {
    foreach (['rental-agreements', 'rental-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/rental-agreements')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantRentalPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/rental-agreements')->assertOk();

    $this->putJson('/api/v1/addons/rental', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/rental-agreements')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'rental');
});

it('creates a draft agreement with a generated number', function () {
    $this->postJson('/api/v1/rental-agreements', [
        'start_at' => now()->toDateString(),
        'end_at' => now()->addWeek()->toDateString(),
        'deposit_amount' => 100,
        'late_fee_per_day' => 5,
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.effective_status', 'draft')
        ->assertJsonPath('data.agreement_number', 'RN-'.date('Y').'-0001')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id)
        ->assertJsonPath('data.total_value', 0);

    $this->postJson('/api/v1/rental-agreements', [
        'start_at' => now()->toDateString(),
        'end_at' => now()->addWeek()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.agreement_number', 'RN-'.date('Y').'-0002');

    $this->postJson('/api/v1/rental-agreements', [
        'start_at' => now()->toDateString(),
        'end_at' => now()->subWeek()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_at']);
});

it('activates a draft and refuses to activate anything else', function () {
    $agreement = rentalAgreement();

    $this->postJson("/api/v1/rental-agreements/{$agreement->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->postJson("/api/v1/rental-agreements/{$agreement->id}/activate")->assertStatus(422);
});

it('cancels a draft or active agreement but not a returned one', function () {
    $draft = rentalAgreement();
    $this->postJson("/api/v1/rental-agreements/{$draft->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $active = RentalAgreement::factory()->active()->create(['company_id' => $this->company->id]);
    $this->postJson("/api/v1/rental-agreements/{$active->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $returned = RentalAgreement::factory()->returned()->create(['company_id' => $this->company->id]);
    $this->postJson("/api/v1/rental-agreements/{$returned->id}/cancel")->assertStatus(422);
});

it('edits and deletes only draft agreements', function () {
    $draft = rentalAgreement();
    $this->putJson("/api/v1/rental-agreements/{$draft->id}", ['deposit_amount' => 25])
        ->assertOk()
        ->assertJsonPath('data.deposit_amount', 25);
    $this->deleteJson("/api/v1/rental-agreements/{$draft->id}")->assertNoContent();

    $active = RentalAgreement::factory()->active()->create(['company_id' => $this->company->id]);
    $this->putJson("/api/v1/rental-agreements/{$active->id}", ['deposit_amount' => 30])->assertOk();
    $this->deleteJson("/api/v1/rental-agreements/{$active->id}")->assertStatus(422);

    $returned = RentalAgreement::factory()->returned()->create(['company_id' => $this->company->id]);
    $this->putJson("/api/v1/rental-agreements/{$returned->id}", ['deposit_amount' => 30])->assertStatus(422);
});

it('adds priced line items and reports the agreement value', function () {
    $agreement = rentalAgreement();

    $this->postJson('/api/v1/rental-items', [
        'rental_agreement_id' => $agreement->id,
        'quantity' => 2,
        'unit_price' => 15,
        'billing_period' => 'day',
    ])
        ->assertCreated()
        ->assertJsonPath('data.subtotal', 30)
        ->assertJsonPath('data.returned_at', null)
        ->assertJsonPath('data.company_id', $this->company->id);

    rentalItem($agreement);

    $this->getJson("/api/v1/rental-agreements/{$agreement->id}")
        ->assertOk()
        ->assertJsonPath('data.total_value', fn ($value) => $value > 30)
        ->assertJsonCount(2, 'data.items');
});

it('refuses to add an item to a closed agreement', function () {
    $returned = RentalAgreement::factory()->returned()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/rental-items', [
        'rental_agreement_id' => $returned->id,
        'quantity' => 1,
        'unit_price' => 10,
        'billing_period' => 'day',
    ])->assertStatus(422);
});

it('returns items one at a time and closes the agreement when all are in', function () {
    $agreement = RentalAgreement::factory()->active()->create(['company_id' => $this->company->id]);
    $first = rentalItem($agreement);
    $second = rentalItem($agreement);

    $this->postJson("/api/v1/rental-items/{$first->id}/return", ['return_condition' => 'damaged'])
        ->assertOk()
        ->assertJsonPath('data.is_returned', true)
        ->assertJsonPath('data.return_condition', 'damaged');

    $this->getJson("/api/v1/rental-agreements/{$agreement->id}")
        ->assertJsonPath('data.status', 'active');

    $this->postJson("/api/v1/rental-items/{$second->id}/return")->assertOk();

    $this->getJson("/api/v1/rental-agreements/{$agreement->id}")
        ->assertJsonPath('data.status', 'returned');

    $this->postJson("/api/v1/rental-items/{$second->id}/return")->assertStatus(422);
});

it('only returns items on an active agreement', function () {
    $draft = rentalAgreement();
    $item = rentalItem($draft);

    $this->postJson("/api/v1/rental-items/{$item->id}/return")->assertStatus(422);
});

it('locks returned items against edits and deletes', function () {
    $agreement = RentalAgreement::factory()->active()->create(['company_id' => $this->company->id]);
    $item = rentalItem($agreement);
    $item->update(['returned_at' => now(), 'return_condition' => 'good']);

    $this->putJson("/api/v1/rental-items/{$item->id}", ['quantity' => 3])->assertStatus(422);
    $this->deleteJson("/api/v1/rental-items/{$item->id}")->assertStatus(422);
});

it('derives overdue status, day count and late fees', function () {
    $agreement = RentalAgreement::factory()->overdue()->create([
        'company_id' => $this->company->id,
        'end_at' => now()->subDays(3)->toDateString(),
        'late_fee_per_day' => 5,
    ]);

    $this->getJson("/api/v1/rental-agreements/{$agreement->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.effective_status', 'overdue')
        ->assertJsonPath('data.days_overdue', 3)
        ->assertJsonPath('data.late_fee', 15);
});

it('summarises agreements, overdue count and deposits held', function () {
    rentalAgreement();
    RentalAgreement::factory()->active()->create(['company_id' => $this->company->id, 'deposit_amount' => 50]);
    RentalAgreement::factory()->returned()->create(['company_id' => $this->company->id]);
    RentalAgreement::factory()->cancelled()->create(['company_id' => $this->company->id]);
    RentalAgreement::factory()->overdue()->create(['company_id' => $this->company->id, 'deposit_amount' => 0]);

    $this->getJson('/api/v1/rental-agreements/summary')
        ->assertOk()
        ->assertJsonPath('summary.total_agreements', 5)
        ->assertJsonPath('summary.by_status.draft', 1)
        ->assertJsonPath('summary.by_status.active', 2)
        ->assertJsonPath('summary.by_status.returned', 1)
        ->assertJsonPath('summary.by_status.cancelled', 1)
        ->assertJsonPath('summary.overdue', 1)
        ->assertJsonPath('summary.deposit_held', 50);
});

it('scopes agreements to the acting user’s company', function () {
    rentalAgreement();
    RentalAgreement::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/rental-agreements')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
