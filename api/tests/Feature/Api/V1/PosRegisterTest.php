<?php

use App\Models\Company;
use App\Models\PosRegister;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\PermissionGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);
});

it('lists registers for the acting user’s company only', function () {
    PosRegister::factory()->create(['company_id' => $this->company->id]);
    PosRegister::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/pos-registers')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonCount(1, 'data');
});

it('creates a register stamped with the acting user’s company', function () {
    $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/pos-registers', [
        'name' => 'Front Till',
        'code' => 'TILL-1',
        'warehouse_id' => $warehouse->id,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Front Till')
        ->assertJsonPath('data.code', 'TILL-1')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.warehouse_id', $warehouse->id)
        ->assertJsonPath('data.is_active', true);

    expect(PosRegister::count())->toBe(1);
});

it('validates the register payload', function () {
    $this->postJson('/api/v1/pos-registers', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->postJson('/api/v1/pos-registers', ['name' => 'Till', 'warehouse_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('updates a register', function () {
    $register = PosRegister::factory()->create(['company_id' => $this->company->id]);

    $this->putJson('/api/v1/pos-registers/'.$register->id, [
        'name' => 'Back Till',
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Back Till')
        ->assertJsonPath('data.is_active', false);
});

it('deletes and restores a register', function () {
    $register = PosRegister::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson('/api/v1/pos-registers/'.$register->id)->assertNoContent();
    expect(PosRegister::find($register->id))->toBeNull();

    $this->postJson('/api/v1/pos-registers/'.$register->id.'/restore')->assertOk();
    expect(PosRegister::find($register->id))->not->toBeNull();
});

it('filters registers by active state and searches by name', function () {
    PosRegister::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Front Till',
        'code' => 'F-1',
    ]);
    PosRegister::factory()->inactive()->create([
        'company_id' => $this->company->id,
        'name' => 'Spare',
        'code' => 'S-1',
    ]);

    expect($this->getJson('/api/v1/pos-registers?is_active=1')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/pos-registers?is_active=0')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/pos-registers?search=Front')->json('data'))->toHaveCount(1);
});

it('keeps the register surface behind the pos-registers permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create(['company_id' => $this->company->id]);
    $plain->assignRole('user');
    Passport::actingAs($plain);

    expect($plain->can('pos-registers.view-any'))->toBeFalse();

    $this->getJson('/api/v1/pos-registers')->assertForbidden();
    $this->postJson('/api/v1/pos-registers', ['name' => 'Till'])->assertForbidden();

    $role = Role::create(['name' => 'cashier', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo([
        'pos-registers.view-any', 'pos-registers.view',
        'pos-registers.create', 'pos-registers.update',
    ]);

    $cashier = User::factory()->create(['company_id' => $this->company->id]);
    $cashier->assignRole($role);
    Passport::actingAs($cashier);

    $this->getJson('/api/v1/pos-registers')->assertOk();

    $register = PosRegister::factory()->create(['company_id' => $this->company->id]);
    $this->putJson('/api/v1/pos-registers/'.$register->id, ['name' => 'Updated'])->assertOk();
    $this->deleteJson('/api/v1/pos-registers/'.$register->id)->assertForbidden();
});
