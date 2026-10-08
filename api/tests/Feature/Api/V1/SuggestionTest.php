<?php

use App\Models\Company;
use App\Models\Suggestion;
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

function suggestionPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Dark mode for the dashboard',
        'body' => 'Working late is easier on the eyes.',
        'category' => 'improvement',
    ], $overrides);
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/suggestions')->assertUnauthorized();
});

it('lists suggestions scoped to the acting user’s company', function () {
    Suggestion::factory()->count(2)->create(['company_id' => $this->company->id]);
    Suggestion::factory()->count(3)->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/suggestions')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('submits a suggestion stamped with the acting user', function () {
    $this->postJson('/api/v1/suggestions', suggestionPayload())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Dark mode for the dashboard')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.is_reviewed', false)
        ->assertJsonPath('data.submitted_by', $this->user->id)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(Suggestion::count())->toBe(1);
});

it('requires a body on a suggestion', function () {
    $this->postJson('/api/v1/suggestions', ['title' => 'Idea only'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('records who reviewed a suggestion and when it moved out of open', function () {
    $suggestion = Suggestion::factory()->create(['company_id' => $this->company->id]);

    $this->putJson("/api/v1/suggestions/{$suggestion->id}", ['status' => 'accepted'])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.is_reviewed', true)
        ->assertJsonPath('data.reviewed_by', $this->user->id);

    expect($suggestion->fresh()->reviewed_at)->not->toBeNull();
});

it('does not stamp a review when the status is unchanged', function () {
    $suggestion = Suggestion::factory()->accepted()->create(['company_id' => $this->company->id]);
    $reviewedAt = $suggestion->reviewed_at;

    $this->putJson("/api/v1/suggestions/{$suggestion->id}", ['body' => 'More detail'])
        ->assertOk()
        ->assertJsonPath('data.reviewed_at', $reviewedAt->toJSON());

    expect($suggestion->fresh()->reviewed_at->equalTo($reviewedAt))->toBeTrue();
});

it('filters by status and category', function () {
    Suggestion::factory()->create(['company_id' => $this->company->id, 'status' => 'open', 'category' => 'improvement']);
    Suggestion::factory()->accepted()->create(['company_id' => $this->company->id, 'category' => 'idea']);

    $this->getJson('/api/v1/suggestions?status=open')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/suggestions?category=idea')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('deletes and restores a suggestion', function () {
    $suggestion = Suggestion::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/suggestions/{$suggestion->id}")->assertNoContent();

    expect(Suggestion::find($suggestion->id))->toBeNull();

    $this->postJson("/api/v1/suggestions/{$suggestion->id}/restore")->assertOk();

    expect(Suggestion::find($suggestion->id))->not->toBeNull();
});

it('blocks a user without the suggestions permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "suggestions.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/suggestions')->assertForbidden();
    $this->postJson('/api/v1/suggestions', suggestionPayload())->assertForbidden();
});
