<?php

use App\Models\Company;
use App\Models\PerformanceReview;
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

function performanceReview(array $overrides = []): PerformanceReview
{
    return PerformanceReview::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $overrides));
}

function grantPerformanceReviewPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create([
            'name' => "performance-reviews.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'performance-reviews.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/performance-reviews')->assertUnauthorized();
});

it('blocks users without the performance permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create([
            'name' => "performance-reviews.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/performance-reviews')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantPerformanceReviewPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/performance-reviews')->assertOk();

    $this->putJson('/api/v1/addons/performance', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/performance-reviews')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'performance');
});

it('creates a review defaulting its status to draft and averaging its scores', function () {
    $response = $this->postJson('/api/v1/performance-reviews', [
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'scores' => ['quality' => 4, 'delivery' => 5, 'teamwork' => 4],
        'comments' => 'Strong quarter.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.overall_score', 4.33)
        ->assertJsonPath('data.period_start', '2026-01-01')
        ->assertJsonPath('data.period_end', '2026-01-31')
        ->assertJsonPath('data.comments', 'Strong quarter.')
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(PerformanceReview::first()?->overall_score)->toBe(4.33);
});

it('rejects an invalid status', function () {
    $this->postJson('/api/v1/performance-reviews', [
        'status' => 'archived',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('approves a draft or submitted review and refuses a second approval', function () {
    $draft = performanceReview();
    $submitted = performanceReview(['status' => PerformanceReview::STATUS_SUBMITTED]);

    $this->postJson("/api/v1/performance-reviews/{$draft->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->postJson("/api/v1/performance-reviews/{$submitted->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->postJson("/api/v1/performance-reviews/{$draft->id}/approve")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a draft or submitted review can be approved.');
});

it('reopens an approved review back to draft and refuses a non-approved one', function () {
    $approved = performanceReview(['status' => PerformanceReview::STATUS_APPROVED]);

    $this->postJson("/api/v1/performance-reviews/{$approved->id}/reopen")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    $this->postJson("/api/v1/performance-reviews/{$approved->id}/reopen")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only an approved review can be reopened.');
});

it('scopes reviews to the acting user’s company', function () {
    performanceReview();
    performanceReview(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/performance-reviews')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
