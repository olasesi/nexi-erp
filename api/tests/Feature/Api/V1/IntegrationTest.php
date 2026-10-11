<?php

use App\Models\Company;
use App\Models\Integration;
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

function grantIntegrationPermissions(User $user): void
{
    foreach (['view-any', 'view', 'update'] as $action) {
        Permission::firstOrCreate([
            'name' => "integrations.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'integrations.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/integrations')->assertUnauthorized();
});

it('blocks users without the integration permissions', function () {
    foreach (['view-any', 'view', 'update'] as $action) {
        Permission::create(['name' => "integrations.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/integrations')->assertForbidden();
});

it('lists the provider catalog grouped by category', function () {
    grantIntegrationPermissions($this->user);

    $response = $this->getJson('/api/v1/integrations')->assertOk();

    $providers = collect($response->json('data'))->pluck('provider');

    expect($providers)->toContain('stripe', 'slack', 'msg91')
        ->and($response->json('meta.categories.payment_gateway'))->toBe('Payment Gateways')
        ->and(collect($response->json('data'))->firstWhere('provider', 'stripe')['status'])
        ->toBe(Integration::STATUS_CONFIGURATION_REQUIRED);
});

it('returns a provider with its fields and masks secrets', function () {
    grantIntegrationPermissions($this->user);

    $response = $this->putJson('/api/v1/integrations/msg91', [
        'auth_key' => 'super-secret-key',
        'sender_id' => 'NEXI',
    ])->assertOk();

    expect($response->json('data.status'))->toBe(Integration::STATUS_CONFIGURATION_REQUIRED);

    $fields = collect($response->json('data.fields'))->keyBy('name');

    expect($fields['auth_key']['value'])->toBe('********')
        ->and($fields['auth_key']['filled'])->toBeTrue()
        ->and($fields['auth_key']['required'])->toBeTrue()
        ->and($fields['sender_id']['value'])->toBe('NEXI')
        ->and($fields['sender_id']['required'])->toBeFalse();
});

it('rejects an unknown provider', function () {
    grantIntegrationPermissions($this->user);

    $this->getJson('/api/v1/integrations/not-a-provider')->assertNotFound();
    $this->putJson('/api/v1/integrations/not-a-provider', [])->assertNotFound();
});

it('refuses to connect a provider with incomplete credentials', function () {
    grantIntegrationPermissions($this->user);

    $this->putJson('/api/v1/integrations/stripe', [
        'publishable_key' => 'pk_test_123',
    ])->assertOk();

    $this->postJson('/api/v1/integrations/stripe/connect')
        ->assertUnprocessable()
        ->assertJsonPath('missing_fields', ['secret_key', 'webhook_secret']);
});

it('connects a fully configured provider', function () {
    grantIntegrationPermissions($this->user);

    $this->putJson('/api/v1/integrations/stripe', [
        'publishable_key' => 'pk_test_123',
        'secret_key' => 'sk_test_123',
        'webhook_secret' => 'whsec_123',
    ])->assertOk();

    $response = $this->postJson('/api/v1/integrations/stripe/connect')->assertOk();

    expect($response->json('data.status'))->toBe(Integration::STATUS_CONNECTED)
        ->and($response->json('data.is_connected'))->toBeTrue()
        ->and($response->json('data.connected_at'))->not->toBeNull();

    $this->assertDatabaseHas('integrations', [
        'company_id' => $this->company->id,
        'provider' => 'stripe',
        'status' => Integration::STATUS_CONNECTED,
        'is_connected' => true,
    ]);
});

it('requires every field when a provider has no secret fields', function () {
    grantIntegrationPermissions($this->user);

    $this->putJson('/api/v1/integrations/einvoice', [
        'electronic_address' => '01927685500000',
    ])->assertOk();

    $this->postJson('/api/v1/integrations/einvoice/connect')
        ->assertUnprocessable()
        ->assertJsonPath('missing_fields', ['company_id', 'software_id', 'software_name']);
});

it('disconnects a connected provider while keeping its credentials', function () {
    grantIntegrationPermissions($this->user);

    $this->putJson('/api/v1/integrations/msg91', ['auth_key' => 'aaa'])->assertOk();
    $this->postJson('/api/v1/integrations/msg91/connect')->assertOk();

    $response = $this->postJson('/api/v1/integrations/msg91/disconnect')->assertOk();

    expect($response->json('data.status'))->toBe(Integration::STATUS_DISABLED)
        ->and($response->json('data.is_connected'))->toBeFalse();

    $detail = $this->getJson('/api/v1/integrations/msg91')->assertOk();
    $fields = collect($detail->json('data.fields'))->keyBy('name');

    expect($fields['auth_key']['filled'])->toBeTrue();
});

it('tests a connected provider and stamps the test time', function () {
    grantIntegrationPermissions($this->user);

    $this->putJson('/api/v1/integrations/msg91', ['auth_key' => 'aaa'])->assertOk();
    $this->postJson('/api/v1/integrations/msg91/connect')->assertOk();

    $response = $this->postJson('/api/v1/integrations/msg91/test')->assertOk();

    expect($response->json('data.last_test_status'))->toBe(Integration::TEST_SUCCESS)
        ->and($response->json('data.last_tested_at'))->not->toBeNull();
});

it('refuses to test a provider that is not connected', function () {
    grantIntegrationPermissions($this->user);

    $this->putJson('/api/v1/integrations/msg91', ['auth_key' => 'aaa'])->assertOk();

    $this->postJson('/api/v1/integrations/msg91/test')->assertUnprocessable();
});

it('scopes connection state to the acting user company', function () {
    grantIntegrationPermissions($this->user);

    $this->putJson('/api/v1/integrations/msg91', ['auth_key' => 'aaa'])->assertOk();
    $this->postJson('/api/v1/integrations/msg91/connect')->assertOk();

    $other = Company::factory()->create();
    $otherUser = User::factory()->create(['company_id' => $other->id]);
    $otherUser->givePermissionTo(Permission::where('name', 'like', 'integrations.%')->get());
    Passport::actingAs($otherUser);

    $response = $this->getJson('/api/v1/integrations/msg91')->assertOk();

    expect($response->json('data.status'))->toBe(Integration::STATUS_CONFIGURATION_REQUIRED)
        ->and($response->json('data.is_connected'))->toBeFalse();

    expect(Integration::withoutGlobalScope('company_scope')
        ->where('company_id', $other->id)->count())->toBe(0);
});
