<?php

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\Rota;
use App\Models\RotaEntry;
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

function rota(): Rota
{
    return Rota::factory()->create(['company_id' => test()->company->id]);
}

function rotaEntry(Rota $rota): RotaEntry
{
    return RotaEntry::factory()->create([
        'company_id' => test()->company->id,
        'rota_id' => $rota->id,
        'employee_id' => HrmEmployee::factory()->create(['company_id' => test()->company->id])->id,
    ]);
}

function grantRotaPermissions(User $user): void
{
    foreach (['rotas', 'rota-entries'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'rota%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/rotas')->assertUnauthorized();
});

it('blocks users without the rota permissions', function () {
    foreach (['rotas', 'rota-entries'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/rotas')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantRotaPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/rotas')->assertOk();

    $this->putJson('/api/v1/addons/rotas', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/rotas')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'rotas');
});

it('creates a rota defaulting its status to draft', function () {
    $this->postJson('/api/v1/rotas', [
        'title' => 'Week 42 Shifts',
        'period_start' => now()->startOfWeek()->toDateString(),
        'period_end' => now()->endOfWeek()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Week 42 Shifts')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('publishes a draft rota and refuses a second publish', function () {
    $rota = rota();

    $this->postJson("/api/v1/rotas/{$rota->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    $this->postJson("/api/v1/rotas/{$rota->id}/publish")->assertStatus(422);
});

it('creates a rota entry tied to a rota', function () {
    $rota = rota();

    $this->postJson('/api/v1/rota-entries', [
        'rota_id' => $rota->id,
        'shift_date' => now()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '17:00',
        'shift_type' => 'morning',
    ])
        ->assertCreated()
        ->assertJsonPath('data.rota_id', $rota->id)
        ->assertJsonPath('data.shift_type', 'morning')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('filters entries by rota', function () {
    $rota = rota();
    $other = rota();
    rotaEntry($rota);
    rotaEntry($other);

    $this->getJson("/api/v1/rota-entries?rota_id={$rota->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rota_id', $rota->id);
});

it('scopes rotas to the acting user’s company', function () {
    rota();
    rota()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/rotas')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
