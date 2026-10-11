<?php

use App\Models\Company;
use App\Models\CompanyPolicy;
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
function companyPolicyRecord(array $attributes = []): CompanyPolicy
{
    return CompanyPolicy::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $attributes));
}

function grantCompanyPolicyPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "company-policies.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'company-policies%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/company-policies')->assertUnauthorized();
});

it('blocks users without the company-policies permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "company-policies.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/company-policies')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantCompanyPolicyPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/company-policies')->assertOk();

    $this->putJson('/api/v1/addons/company-policy', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/company-policies')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'company-policy');
});

it('creates a policy defaulting its status to draft', function () {
    $this->postJson('/api/v1/company-policies', [
        'title' => 'Remote Work Policy',
        'content' => 'Employees may work remotely two days per week.',
        'version' => '1.0',
        'effective_date' => now()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Remote Work Policy')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.is_published', false)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects an invalid status', function () {
    $this->postJson('/api/v1/company-policies', [
        'title' => 'Remote Work Policy',
        'status' => 'expired',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('publishes a draft policy and refuses to publish it twice', function () {
    $policy = companyPolicyRecord();

    $this->postJson("/api/v1/company-policies/{$policy->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.is_published', true);

    $this->postJson("/api/v1/company-policies/{$policy->id}/publish")->assertStatus(422);
});

it('refuses to archive a draft policy but archives a published one', function () {
    $draft = companyPolicyRecord();

    $this->postJson("/api/v1/company-policies/{$draft->id}/archive")->assertStatus(422);

    $published = companyPolicyRecord(['status' => CompanyPolicy::STATUS_PUBLISHED]);

    $this->postJson("/api/v1/company-policies/{$published->id}/archive")
        ->assertOk()
        ->assertJsonPath('data.status', 'archived')
        ->assertJsonPath('data.is_published', false);
});

it('filters policies by status', function () {
    companyPolicyRecord(['status' => CompanyPolicy::STATUS_DRAFT]);
    companyPolicyRecord(['status' => CompanyPolicy::STATUS_PUBLISHED]);

    $this->getJson('/api/v1/company-policies?status=published')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'published');
});

it('scopes policies to the acting user’s company', function () {
    companyPolicyRecord();
    companyPolicyRecord()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/company-policies')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
