<?php

use App\Models\Announcement;
use App\Models\Company;
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
function announcement(array $attributes = []): Announcement
{
    return Announcement::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $attributes));
}

function grantAnnouncementPermissions(User $user): void
{
    foreach (['announcements'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'announcements%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/announcements')->assertUnauthorized();
});

it('blocks users without the announcement permissions', function () {
    foreach (['announcements'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/announcements')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantAnnouncementPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/announcements')->assertOk();

    $this->putJson('/api/v1/addons/announcement', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/announcements')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'announcement');
});

it('creates an announcement defaulting to draft, all and normal', function () {
    $this->postJson('/api/v1/announcements', [
        'title' => 'Office closed on Friday',
        'body' => 'The office will be closed for maintenance.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Office closed on Friday')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.audience', 'all')
        ->assertJsonPath('data.priority', 'normal')
        ->assertJsonPath('data.is_published', false)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('publishes a draft announcement and refuses to publish it twice', function () {
    $announcement = announcement();

    $this->postJson("/api/v1/announcements/{$announcement->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.is_published', true);

    $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertStatus(422);
});

it('archives a published announcement and refuses to archive a draft', function () {
    $published = announcement(['status' => Announcement::STATUS_PUBLISHED]);

    $this->postJson("/api/v1/announcements/{$published->id}/archive")
        ->assertOk()
        ->assertJsonPath('data.status', 'archived')
        ->assertJsonPath('data.is_published', false);

    $draft = announcement();

    $this->postJson("/api/v1/announcements/{$draft->id}/archive")->assertStatus(422);
});

it('filters announcements by status', function () {
    announcement(['status' => Announcement::STATUS_DRAFT]);
    announcement(['status' => Announcement::STATUS_PUBLISHED]);

    $this->getJson('/api/v1/announcements?status=published')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'published');
});

it('scopes announcements to the acting user’s company', function () {
    announcement();
    announcement()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
