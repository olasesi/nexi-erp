<?php

use App\Models\Company;
use App\Models\DigitalCertificate;
use App\Models\HrmEmployee;
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

function digitalCertificate(array $attributes = []): DigitalCertificate
{
    return DigitalCertificate::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'employee_id' => HrmEmployee::factory()->create(['company_id' => test()->company->id])->id,
    ], $attributes));
}

function grantDigitalCertificatePermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create([
            'name' => "digital-certificates.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'digital-certificates.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/digital-certificates')->assertUnauthorized();
});

it('blocks users without the digital certificate permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "digital-certificates.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/digital-certificates')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantDigitalCertificatePermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/digital-certificates')->assertOk();

    $this->putJson('/api/v1/addons/digital-certificate', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/digital-certificates')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'digital-certificate');
});

it('creates a certificate defaulting its status to draft', function () {
    $this->postJson('/api/v1/digital-certificates', [
        'title' => 'Forklift Operator',
        'certificate_type' => 'Training',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Forklift Operator')
        ->assertJsonPath('data.certificate_type', 'Training')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('generates a draft certificate and refuses a second generate', function () {
    $certificate = digitalCertificate();

    $this->postJson("/api/v1/digital-certificates/{$certificate->id}/generate")
        ->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.issued_date', today()->toDateString());

    $certificate->refresh();

    expect($certificate->serial)->not->toBeNull();
    expect($certificate->certificate_number)->not->toBeNull();

    $this->postJson("/api/v1/digital-certificates/{$certificate->id}/generate")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a draft certificate can be generated.');
});

it('revokes an issued certificate and refuses revoking a draft', function () {
    $issued = digitalCertificate(['status' => DigitalCertificate::STATUS_ISSUED]);

    $this->postJson("/api/v1/digital-certificates/{$issued->id}/revoke", [
        'revoked_reason' => 'Issued in error.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked')
        ->assertJsonPath('data.revoked_reason', 'Issued in error.');

    expect($issued->fresh()->revoked_at)->not->toBeNull();

    $draft = digitalCertificate();

    $this->postJson("/api/v1/digital-certificates/{$draft->id}/revoke", [
        'revoked_reason' => 'Cannot revoke a draft.',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only an issued certificate can be revoked.');
});

it('filters certificates by status', function () {
    digitalCertificate(['status' => DigitalCertificate::STATUS_DRAFT]);
    digitalCertificate(['status' => DigitalCertificate::STATUS_ISSUED]);

    $this->getJson('/api/v1/digital-certificates?status=issued')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'issued');
});

it('scopes certificates to the acting user’s company', function () {
    digitalCertificate();

    $other = Company::factory()->create();
    DigitalCertificate::factory()->create([
        'company_id' => $other->id,
        'employee_id' => HrmEmployee::factory()->create(['company_id' => $other->id])->id,
    ]);

    $this->getJson('/api/v1/digital-certificates')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
