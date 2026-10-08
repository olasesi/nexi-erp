<?php

use App\Models\Company;
use App\Models\Notice;
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

function noticePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Office closed on Friday',
        'body' => 'Building maintenance from 18:00.',
        'priority' => 'high',
    ], $overrides);
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/notices')->assertUnauthorized();
});

it('lists live notices for the acting user’s company', function () {
    Notice::factory()->create(['company_id' => $this->company->id]);
    Notice::factory()->draft()->create(['company_id' => $this->company->id]);
    Notice::factory()->expired()->create(['company_id' => $this->company->id]);
    Notice::factory()->create(['company_id' => Company::factory()->create()->id]);

    $response = $this->getJson('/api/v1/notices')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.is_active'))->toBeTrue();
});

it('returns drafts and expired notices for the management view', function () {
    Notice::factory()->create(['company_id' => $this->company->id]);
    Notice::factory()->draft()->create(['company_id' => $this->company->id]);
    Notice::factory()->expired()->create(['company_id' => $this->company->id]);

    $this->getJson('/api/v1/notices?status=all')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a notice stamped with the acting user’s company and author', function () {
    $this->postJson('/api/v1/notices', noticePayload())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Office closed on Friday')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id)
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.published_at', now()->toDateString());

    expect(Notice::count())->toBe(1);
});

it('honours an explicit publish and expiry window', function () {
    $this->postJson('/api/v1/notices', noticePayload([
        'published_at' => now()->addDays(3)->toDateString(),
        'expires_at' => now()->addDays(10)->toDateString(),
    ]))->assertCreated()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.published_at', now()->addDays(3)->toDateString())
        ->assertJsonPath('data.expires_at', now()->addDays(10)->toDateString());
});

it('validates the notice payload', function () {
    $this->postJson('/api/v1/notices', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);

    $this->postJson('/api/v1/notices', noticePayload(['priority' => 'urgent-ish']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});

it('shows a notice', function () {
    $notice = Notice::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Fire drill at 15:00',
    ]);

    $this->getJson("/api/v1/notices/{$notice->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Fire drill at 15:00');
});

it('updates a notice', function () {
    $notice = Notice::factory()->create(['company_id' => $this->company->id, 'title' => 'Old']);

    $this->putJson("/api/v1/notices/{$notice->id}", ['title' => 'New', 'priority' => 'urgent'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New')
        ->assertJsonPath('data.priority', 'urgent');

    expect($notice->fresh()->title)->toBe('New');
});

it('deletes and restores a notice', function () {
    $notice = Notice::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/notices/{$notice->id}")->assertNoContent();

    expect(Notice::find($notice->id))->toBeNull()
        ->and(Notice::withTrashed()->find($notice->id))->not->toBeNull();

    $this->postJson("/api/v1/notices/{$notice->id}/restore")->assertOk();

    expect(Notice::find($notice->id))->not->toBeNull();
});

it('searches title and body', function () {
    Notice::factory()->create(['company_id' => $this->company->id, 'title' => 'Quarterly town hall']);
    Notice::factory()->create(['company_id' => $this->company->id, 'body' => 'Bring your laptop']);

    $this->getJson('/api/v1/notices?search=town')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('blocks a user without the notices permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "notices.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/notices')->assertForbidden();
    $this->postJson('/api/v1/notices', noticePayload())->assertForbidden();
});
