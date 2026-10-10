<?php

use App\Models\Company;
use App\Models\Form;
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

function form(): Form
{
    return Form::factory()->create(['company_id' => test()->company->id]);
}

function grantFormPermissions(User $user): void
{
    $ids = [];

    foreach (['forms', 'form-fields', 'form-submissions'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            $ids[] = Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ])->id;
        }
    }

    $user->givePermissionTo(Permission::whereIn('id', $ids)->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/forms')->assertUnauthorized();
});

it('blocks users without the form permissions', function () {
    foreach (['forms', 'form-fields', 'form-submissions'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/forms')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantFormPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/forms')->assertOk();

    $this->putJson('/api/v1/addons/form-builder', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/forms')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'form-builder');
});

it('creates a form with the default draft status and an empty submission count', function () {
    $this->postJson('/api/v1/forms', [
        'name' => 'Customer Feedback',
        'description' => 'How was your experience?',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Customer Feedback')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.is_template', false)
        ->assertJsonPath('data.submissions_count', 0)
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id);
});

it('publishes a draft form and marks it active', function () {
    $form = form();

    $this->postJson("/api/v1/forms/{$form->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('refuses to publish an archived form', function () {
    $form = Form::factory()->archived()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/forms/{$form->id}/publish")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects a field with an invalid type', function () {
    $form = form();

    $this->postJson('/api/v1/form-fields', [
        'form_id' => $form->id,
        'label' => 'Nickname',
        'name' => 'nickname',
        'type' => 'color-picker',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('stores a submission payload and increments the submissions counter', function () {
    $form = Form::factory()->active()->create(['company_id' => $this->company->id]);

    $payload = ['name' => 'Ada Lovelace', 'topic' => 'Analytical engine'];

    $this->postJson('/api/v1/form-submissions', [
        'form_id' => $form->id,
        'payload' => $payload,
    ])
        ->assertCreated()
        ->assertJsonPath('data.form_id', $form->id)
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.submitted_by', $this->user->id)
        ->assertJsonPath('data.payload.name', 'Ada Lovelace')
        ->assertJsonPath('data.payload.topic', 'Analytical engine');

    $this->getJson("/api/v1/forms/{$form->id}")
        ->assertOk()
        ->assertJsonPath('data.submissions_count', 1);
});

it('rejects submissions against an archived form', function () {
    $form = Form::factory()->archived()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/form-submissions', [
        'form_id' => $form->id,
        'payload' => ['name' => 'Grace Hopper'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['form_id']);
});

it('scopes forms to the acting user’s company', function () {
    form();
    form()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/forms')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
