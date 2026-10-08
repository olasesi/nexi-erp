<?php

use App\Models\Company;
use App\Models\User;
use App\Services\AddonService;
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

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/addons')->assertUnauthorized();
});

it('lists the catalog with each add-on’s effective toggle state', function () {
    $response = $this->getJson('/api/v1/addons')->assertOk();

    expect($response->json('data'))->toHaveCount(count(config('addons')));
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe(array_keys(config('addons')));

    $noticeBoard = collect($response->json('data'))->firstWhere('id', 'notice-board');

    expect($noticeBoard['enabled'])->toBeTrue()
        ->and($noticeBoard['status'])->toBe('free')
        ->and($noticeBoard['label'])->toBe('Notice Board');
});

it('toggles an add-on off for the acting user’s company only', function () {
    $this->putJson('/api/v1/addons/notice-board', ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.id', 'notice-board')
        ->assertJsonPath('data.enabled', false);

    expect(app(AddonService::class)->enabled('notice-board', $this->company->id))->toBeFalse();

    $other = Company::factory()->create();

    expect(app(AddonService::class)->enabled('notice-board', $other->id))->toBeTrue();
});

it('turns an add-on back on', function () {
    $this->putJson('/api/v1/addons/suggestion-box', ['enabled' => false])->assertOk();

    $this->putJson('/api/v1/addons/suggestion-box', ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.enabled', true);

    expect(app(AddonService::class)->enabled('suggestion-box', $this->company->id))->toBeTrue();
});

it('404s on an add-on that is not in the catalog', function () {
    $this->putJson('/api/v1/addons/does-not-exist', ['enabled' => true])->assertNotFound();
});

it('validates the toggle payload', function () {
    $this->putJson('/api/v1/addons/notice-board', [])->assertUnprocessable();
});

it('blocks the toggle without the addons.update permission', function () {
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);

    $this->putJson('/api/v1/addons/notice-board', ['enabled' => false])->assertForbidden();
});

it('allows the toggle with the addons.update permission', function () {
    $permission = Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo($permission);

    $this->putJson('/api/v1/addons/notice-board', ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);
});

it('keeps the catalog readable while an add-on is switched off', function () {
    $this->putJson('/api/v1/addons/notice-board', ['enabled' => false])->assertOk();

    $response = $this->getJson('/api/v1/addons')->assertOk();

    expect(collect($response->json('data'))->firstWhere('id', 'notice-board')['enabled'])->toBeFalse();
});

it('gates add-on routes behind the toggle with a stable code', function () {
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "notices.{$action}", 'guard_name' => PermissionGuard::name()]);
    }
    $this->user->givePermissionTo(
        Permission::where('name', 'like', 'notices.%')
            ->orWhere('name', 'addons.update')
            ->get()
    );

    $this->getJson('/api/v1/notices')->assertOk();

    $this->putJson('/api/v1/addons/notice-board', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/notices')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'notice-board');
});
