<?php

use App\Models\Company;
use App\Models\Notification;
use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);
});

function notificationPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Low stock on SKU-1001',
        'body' => 'Warehouse A has 3 units left.',
        'type' => 'stock',
        'payload' => ['sku' => 'SKU-1001'],
    ], $overrides);
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});

it('lists notifications scoped to the acting user’s company', function () {
    $other = Company::factory()->create();
    Notification::factory()->count(2)->create(['company_id' => $this->company->id]);
    Notification::factory()->count(3)->create(['company_id' => $other->id]);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists broadcast notifications and personal ones addressed to me', function () {
    $colleague = User::factory()->create(['company_id' => $this->company->id]);

    Notification::factory()->broadcast()->create(['company_id' => $this->company->id]);
    Notification::factory()->create(['company_id' => $this->company->id, 'user_id' => $colleague->id]);
    Notification::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);

    $response = $this->getJson('/api/v1/notifications')->assertOk();

    expect(collect($response->json('data'))->pluck('user_id')->all())
        ->each->toBeIn([null, $this->user->id]);
});

it('filters to unread notifications', function () {
    Notification::factory()->create(['company_id' => $this->company->id, 'read_at' => now()]);
    Notification::factory()->create(['company_id' => $this->company->id]);

    $response = $this->getJson('/api/v1/notifications?unread=1')->assertOk();

    expect(collect($response->json('data'))->pluck('is_read')->all())->each->toBeFalse();
});

it('filters by type', function () {
    Notification::factory()->create(['company_id' => $this->company->id, 'type' => 'stock']);
    Notification::factory()->create(['company_id' => $this->company->id, 'type' => 'invoice']);

    $response = $this->getJson('/api/v1/notifications?type=invoice')->assertOk();

    expect(collect($response->json('data'))->pluck('type')->all())->toBe(['invoice']);
});

it('searches title and body', function () {
    Notification::factory()->create(['company_id' => $this->company->id, 'title' => 'Payment received']);
    Notification::factory()->create(['company_id' => $this->company->id, 'body' => 'Please review stock levels']);

    $response = $this->getJson('/api/v1/notifications?search=stock')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('creates a broadcast notification with the acting user’s company', function () {
    $this->postJson('/api/v1/notifications', notificationPayload())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Low stock on SKU-1001')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.is_read', false)
        ->assertJsonPath('data.payload.sku', 'SKU-1001');

    expect(Notification::count())->toBe(1);
});

it('creates a notification addressed to a specific user', function () {
    $target = User::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/notifications', notificationPayload(['user_id' => $target->id]))
        ->assertCreated()
        ->assertJsonPath('data.user_id', $target->id);

    expect(Notification::firstOrFail()->user_id)->toBe($target->id);
});

it('shows a notification', function () {
    $notification = Notification::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Overdue invoice INV-2024-00001',
    ]);

    $this->getJson("/api/v1/notifications/{$notification->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id)
        ->assertJsonPath('data.title', 'Overdue invoice INV-2024-00001');
});

it('updates a notification including read state', function () {
    $notification = Notification::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Old title',
    ]);

    $this->putJson("/api/v1/notifications/{$notification->id}", [
        'title' => 'New title',
        'is_read' => true,
    ])->assertOk()
        ->assertJsonPath('data.title', 'New title')
        ->assertJsonPath('data.is_read', true);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('reports the number of unread notifications for the acting user', function () {
    Notification::factory()->broadcast()->create(['company_id' => $this->company->id]);
    Notification::factory()->broadcast()->create(['company_id' => $this->company->id, 'read_at' => now()]);
    Notification::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
    Notification::factory()->create(['company_id' => $this->company->id, 'user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('unread_count', 2);
});

it('marks all visible notifications as read', function () {
    $colleague = User::factory()->create(['company_id' => $this->company->id]);

    Notification::factory()->broadcast()->create(['company_id' => $this->company->id]);
    Notification::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
    Notification::factory()->create(['company_id' => $this->company->id, 'user_id' => $colleague->id]);

    $this->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('updated', 2);

    expect(Notification::where('user_id', $this->user->id)->value('read_at'))->not->toBeNull()
        ->and(Notification::whereNull('user_id')->value('read_at'))->not->toBeNull()
        ->and(Notification::where('user_id', $colleague->id)->value('read_at'))->toBeNull();
});

it('deletes a notification', function () {
    $notification = Notification::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/notifications/{$notification->id}")->assertNoContent();

    expect(Notification::find($notification->id))->toBeNull();
});

it('blocks a user without the matching permission', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "notifications.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    $user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($user);

    $this->getJson('/api/v1/notifications')->assertForbidden();
    $this->postJson('/api/v1/notifications', notificationPayload())->assertForbidden();
});

it('allows a user who holds the notification permissions through a role', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "notifications.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    $role = Role::create(['name' => 'notifier', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['notifications.view-any', 'notifications.create']);

    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole($role);
    Passport::actingAs($user);

    $this->getJson('/api/v1/notifications')->assertOk();
    $this->postJson('/api/v1/notifications', notificationPayload())->assertCreated();
});

it('counts created notifications in the metrics exposition', function () {
    $this->postJson('/api/v1/notifications', notificationPayload())->assertCreated();

    $this->get('/api/metrics')
        ->assertOk()
        ->assertSee('nexi_erp_notifications_created_total 1', false)
        ->assertSee('nexi_erp_notifications_total{status="unread"} 1', false);
});
