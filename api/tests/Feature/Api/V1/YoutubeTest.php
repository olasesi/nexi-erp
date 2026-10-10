<?php

use App\Models\Company;
use App\Models\User;
use App\Models\YoutubePlaylist;
use App\Models\YoutubeVideo;
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

function grantYoutubePermissions(User $user): void
{
    foreach (['youtube-videos', 'youtube-playlists', 'youtube-channel', 'youtube-activity'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'youtube-%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/youtube-videos')->assertUnauthorized();
});

it('blocks users without the youtube permissions', function () {
    foreach (['youtube-videos', 'youtube-playlists', 'youtube-channel', 'youtube-activity'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/youtube-videos')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantYoutubePermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/youtube-videos')->assertOk();

    $this->putJson('/api/v1/addons/youtube', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/youtube-videos')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'youtube');
});

it('creates a channel marked as connected', function () {
    grantYoutubePermissions($this->user);

    $this->postJson('/api/v1/youtube-channel', [
        'channel_id' => 'UC1234567890',
        'title' => 'Nexi TV',
        'is_connected' => true,
        'connected_at' => now()->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.channel_id', 'UC1234567890')
        ->assertJsonPath('data.title', 'Nexi TV')
        ->assertJsonPath('data.is_connected', true)
        ->assertJsonPath('data.company_id', $this->company->id);

    $this->assertDatabaseHas('youtube_channel', [
        'company_id' => $this->company->id,
        'channel_id' => 'UC1234567890',
        'is_connected' => true,
    ]);
});

it('creates a playlist', function () {
    grantYoutubePermissions($this->user);

    $this->postJson('/api/v1/youtube-playlists', [
        'title' => 'Tutorials',
        'visibility' => 'public',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Tutorials')
        ->assertJsonPath('data.visibility', 'public')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('creates a draft video attached to a playlist', function () {
    grantYoutubePermissions($this->user);
    $playlist = YoutubePlaylist::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/youtube-videos', [
        'title' => 'Getting started',
        'youtube_playlist_id' => $playlist->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Getting started')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.youtube_playlist_id', $playlist->id)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('records an activity', function () {
    grantYoutubePermissions($this->user);

    $this->postJson('/api/v1/youtube-activity', [
        'type' => 'upload',
        'description' => 'Uploaded a video',
        'occurred_at' => now()->toDateTimeString(),
        'meta' => ['source' => 'manual'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'upload')
        ->assertJsonPath('data.description', 'Uploaded a video')
        ->assertJsonPath('data.meta.source', 'manual')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('lists videos filtered by status', function () {
    grantYoutubePermissions($this->user);
    YoutubeVideo::factory()->create([
        'company_id' => $this->company->id,
        'status' => YoutubeVideo::STATUS_DRAFT,
    ]);
    YoutubeVideo::factory()->create([
        'company_id' => $this->company->id,
        'status' => YoutubeVideo::STATUS_PUBLISHED,
    ]);

    $this->getJson('/api/v1/youtube-videos?status=published')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'published');
});

it('rejects an unsupported video status', function () {
    grantYoutubePermissions($this->user);

    $this->postJson('/api/v1/youtube-videos', [
        'title' => 'Bad status',
        'status' => 'archived',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('scopes videos to the acting user company', function () {
    grantYoutubePermissions($this->user);
    YoutubeVideo::factory()->create(['company_id' => $this->company->id]);
    YoutubeVideo::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/youtube-videos')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
