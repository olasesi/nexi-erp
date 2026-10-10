<?php

use App\Models\Company;
use App\Models\Training;
use App\Models\TrainingType;
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

function trainingType(): TrainingType
{
    return TrainingType::factory()->create(['company_id' => test()->company->id]);
}

function training(?TrainingType $type = null): Training
{
    return Training::factory()->create([
        'company_id' => test()->company->id,
        'training_type_id' => ($type ?? trainingType())->id,
    ]);
}

function grantTrainingPermissions(User $user): void
{
    foreach (['trainings', 'training-types'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'training%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/trainings')->assertUnauthorized();
});

it('blocks users without the training permissions', function () {
    foreach (['trainings', 'training-types'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/trainings')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantTrainingPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/trainings')->assertOk();

    $this->putJson('/api/v1/addons/training', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/trainings')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'training');
});

it('creates a training type', function () {
    $this->postJson('/api/v1/training-types', [
        'name' => 'Workplace Safety',
        'branch' => 'HQ',
        'department' => 'Operations',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Workplace Safety')
        ->assertJsonPath('data.branch', 'HQ')
        ->assertJsonPath('data.department', 'Operations')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('creates a training defaulting its status to scheduled', function () {
    $type = trainingType();

    $this->postJson('/api/v1/trainings', [
        'training_type_id' => $type->id,
        'title' => 'Forklift Safety',
        'trainer' => 'Ada Lovelace',
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDay()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Forklift Safety')
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.type.id', $type->id)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects an invalid status', function () {
    $this->postJson('/api/v1/trainings', [
        'title' => 'Forklift Safety',
        'status' => 'paused',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('cancels a training and refuses to cancel it twice', function () {
    $training = training();

    $this->postJson("/api/v1/trainings/{$training->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->postJson("/api/v1/trainings/{$training->id}/cancel")->assertStatus(422);
});

it('filters trainings by type', function () {
    $type = trainingType();
    training($type);
    training();

    $this->getJson("/api/v1/trainings?training_type_id={$type->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.training_type_id', $type->id);
});

it('searches trainings by title and trainer', function () {
    training()->update(['title' => 'Forklift Safety', 'trainer' => 'Grace Hopper']);
    training()->update(['title' => 'First Aid', 'trainer' => 'Alan Turing']);

    $this->getJson('/api/v1/trainings?search=Forklift')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/trainings?search=Alan')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('scopes trainings to the acting user’s company', function () {
    training();
    training()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/trainings')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
