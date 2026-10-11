<?php

use App\Models\Company;
use App\Models\KbArticle;
use App\Models\KbCategory;
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

function kbCategory(): KbCategory
{
    return KbCategory::factory()->create(['company_id' => test()->company->id]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function kbArticle(?KbCategory $category = null, array $attributes = []): KbArticle
{
    return KbArticle::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'kb_category_id' => ($category ?? kbCategory())->id,
    ], $attributes));
}

function grantKnowledgeBasePermissions(User $user): void
{
    foreach (['kb-categories', 'kb-articles'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'kb-%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/kb-articles')->assertUnauthorized();
});

it('blocks users without the knowledge base permissions', function () {
    foreach (['kb-categories', 'kb-articles'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/kb-articles')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantKnowledgeBasePermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/kb-articles')->assertOk();

    $this->putJson('/api/v1/addons/knowledge-base', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/kb-articles')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'knowledge-base');
});

it('creates a category', function () {
    $this->postJson('/api/v1/kb-categories', [
        'name' => 'Getting Started',
        'description' => 'Onboarding material.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Getting Started')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('creates an article defaulting its status to draft', function () {
    $category = kbCategory();

    $this->postJson('/api/v1/kb-articles', [
        'kb_category_id' => $category->id,
        'title' => 'How to reset your password',
        'body' => 'Click the reset link.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'How to reset your password')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects an invalid status', function () {
    $this->postJson('/api/v1/kb-articles', [
        'title' => 'How to reset your password',
        'status' => 'archived',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('publishes a draft article and refuses to publish it twice', function () {
    $article = kbArticle();

    $this->postJson("/api/v1/kb-articles/{$article->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.is_published', true);

    $this->postJson("/api/v1/kb-articles/{$article->id}/publish")
        ->assertStatus(422);
});

it('records a view each time an article is fetched', function () {
    $article = kbArticle();

    $this->getJson("/api/v1/kb-articles/{$article->id}")
        ->assertOk()
        ->assertJsonPath('data.views', 1);

    $this->getJson("/api/v1/kb-articles/{$article->id}")
        ->assertOk()
        ->assertJsonPath('data.views', 2);
});

it('filters articles by status', function () {
    kbArticle(null, ['status' => KbArticle::STATUS_DRAFT]);
    kbArticle(null, ['status' => KbArticle::STATUS_PUBLISHED, 'published_at' => now()]);

    $this->getJson('/api/v1/kb-articles?status=published')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'published');
});

it('scopes articles to the acting user’s company', function () {
    kbArticle();
    kbArticle(null, ['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/kb-articles')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
