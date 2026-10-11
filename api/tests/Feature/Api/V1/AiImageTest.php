<?php

use App\Models\AiImage;
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

function aiImageRecord(): AiImage
{
    return AiImage::factory()->create(['company_id' => test()->company->id]);
}

function grantAiImagePermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "ai-images.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'ai-images.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/ai-images')->assertUnauthorized();
});

it('blocks users without the ai image permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "ai-images.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/ai-images')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantAiImagePermissions($this->user);
    Permission::firstOrCreate(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/ai-images')->assertOk();

    $this->putJson('/api/v1/addons/ai-image', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/ai-images')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'ai-image');
});

it('creates a pending image owned by the acting user', function () {
    grantAiImagePermissions($this->user);

    $this->postJson('/api/v1/ai-images', [
        'prompt' => 'A minimalist logo for a fintech brand',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', AiImage::STATUS_PENDING)
        ->assertJsonPath('data.size', '512x512')
        ->assertJsonPath('data.url', null)
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.user_id', $this->user->id);
});

it('generates an image through the configured driver', function () {
    grantAiImagePermissions($this->user);

    $image = aiImageRecord();

    $response = $this->postJson("/api/v1/ai-images/{$image->id}/generate")
        ->assertOk()
        ->assertJsonPath('data.status', AiImage::STATUS_GENERATED)
        ->assertJsonPath('data.provider', 'stub')
        ->assertJsonPath('data.model', 'stub-image-1');

    expect($response->json('data.url'))->toStartWith('data:image/svg+xml;base64,');
    expect($response->json('data.generated_at'))->not->toBeNull();
});

it('honours the requested size in the generated stub image', function () {
    grantAiImagePermissions($this->user);

    $image = AiImage::factory()->create([
        'company_id' => $this->company->id,
        'size' => '256x128',
    ]);

    $this->postJson("/api/v1/ai-images/{$image->id}/generate")->assertOk();

    $svg = base64_decode(substr((string) $image->fresh()->url, strlen('data:image/svg+xml;base64,')));

    expect($svg)->toContain('width="256"')->toContain('height="128"');
});

it('filters and searches images', function () {
    grantAiImagePermissions($this->user);

    AiImage::factory()->create(['company_id' => $this->company->id, 'prompt' => 'Blue banner', 'status' => AiImage::STATUS_PENDING]);
    AiImage::factory()->generated()->create(['company_id' => $this->company->id, 'prompt' => 'Red banner']);

    $this->getJson('/api/v1/ai-images?status=generated')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.prompt', 'Red banner');

    $this->getJson('/api/v1/ai-images?search=Blue')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.prompt', 'Blue banner');
});

it('updates, deletes and restores an image', function () {
    grantAiImagePermissions($this->user);

    $image = aiImageRecord();

    $this->putJson("/api/v1/ai-images/{$image->id}", ['style' => 'watercolor'])
        ->assertOk()
        ->assertJsonPath('data.style', 'watercolor');

    $this->deleteJson("/api/v1/ai-images/{$image->id}")->assertNoContent();

    $this->postJson("/api/v1/ai-images/{$image->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.style', 'watercolor');
});

it('scopes images to the acting user’s company', function () {
    grantAiImagePermissions($this->user);

    aiImageRecord();
    AiImage::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/ai-images')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
