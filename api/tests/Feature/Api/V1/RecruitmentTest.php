<?php

use App\Models\Company;
use App\Models\JobCandidate;
use App\Models\JobPosting;
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

function recruitmentPosting(array $attributes = []): JobPosting
{
    return JobPosting::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $attributes));
}

function recruitmentCandidate(array $attributes = []): JobCandidate
{
    $attributes['company_id'] ??= test()->company->id;

    if (! array_key_exists('job_posting_id', $attributes)) {
        $attributes['job_posting_id'] = recruitmentPosting()->id;
    }

    return JobCandidate::factory()->create($attributes);
}

function grantRecruitmentPermissions(User $user): void
{
    foreach (['job-postings', 'job-candidates'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(
        Permission::whereIn('name', [
            'job-postings.view-any', 'job-postings.view', 'job-postings.create', 'job-postings.update', 'job-postings.delete',
            'job-candidates.view-any', 'job-candidates.view', 'job-candidates.create', 'job-candidates.update', 'job-candidates.delete',
        ])->get(),
    );
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/job-postings')->assertUnauthorized();
    $this->getJson('/api/v1/job-candidates')->assertUnauthorized();
});

it('blocks users without the recruitment permissions', function () {
    foreach (['job-postings', 'job-candidates'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/job-postings')->assertForbidden();
    $this->getJson('/api/v1/job-candidates')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantRecruitmentPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/job-postings')->assertOk();
    $this->getJson('/api/v1/job-candidates')->assertOk();

    $this->putJson('/api/v1/addons/recruitment', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/job-postings')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'recruitment');

    $this->getJson('/api/v1/job-candidates')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'recruitment');
});

it('creates a job posting defaulting its status to draft', function () {
    $this->postJson('/api/v1/job-postings', [
        'title' => 'Senior Engineer',
        'department' => 'Engineering',
        'location' => 'Remote',
        'employment_type' => 'full_time',
        'salary_min' => 80000,
        'salary_max' => 120000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Senior Engineer')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.openings', 1)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects an invalid employment type', function () {
    $this->postJson('/api/v1/job-postings', [
        'title' => 'Senior Engineer',
        'employment_type' => 'gig',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_type']);
});

it('publishes a draft posting and refuses to publish it twice', function () {
    $posting = recruitmentPosting();

    $this->postJson("/api/v1/job-postings/{$posting->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.published_at', now()->format('Y-m-d H:i:s'));

    $this->postJson("/api/v1/job-postings/{$posting->id}/publish")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a draft posting can be published.');
});

it('closes a published posting and refuses to close a draft', function () {
    $posting = recruitmentPosting(['status' => JobPosting::STATUS_PUBLISHED, 'published_at' => now()]);

    $this->postJson("/api/v1/job-postings/{$posting->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    $draft = recruitmentPosting();

    $this->postJson("/api/v1/job-postings/{$draft->id}/close")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a published posting can be closed.');
});

it('creates a candidate defaulting its stage to applied', function () {
    $posting = recruitmentPosting();

    $this->postJson('/api/v1/job-candidates', [
        'job_posting_id' => $posting->id,
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Grace Hopper')
        ->assertJsonPath('data.stage', 'applied')
        ->assertJsonPath('data.job_posting_id', $posting->id)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('hires a candidate, stamps hired_at and refuses to finalise them twice', function () {
    $this->travelTo(now());

    $candidate = recruitmentCandidate();

    $this->postJson("/api/v1/job-candidates/{$candidate->id}/hire")
        ->assertOk()
        ->assertJsonPath('data.stage', 'hired')
        ->assertJsonPath('data.hired_at', now()->format('Y-m-d H:i:s'));

    $this->postJson("/api/v1/job-candidates/{$candidate->id}/hire")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This candidate has already been finalised.');

    $this->postJson("/api/v1/job-candidates/{$candidate->id}/reject")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This candidate has already been finalised.');
});

it('rejects a candidate and refuses to hire them afterwards', function () {
    $candidate = recruitmentCandidate();

    $this->postJson("/api/v1/job-candidates/{$candidate->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.stage', 'rejected')
        ->assertJsonPath('data.hired_at', null);

    $this->postJson("/api/v1/job-candidates/{$candidate->id}/hire")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This candidate has already been finalised.');
});

it('filters candidates by posting and stage', function () {
    $posting = recruitmentPosting();
    $other = recruitmentPosting();
    recruitmentCandidate(['job_posting_id' => $posting->id, 'stage' => JobCandidate::STAGE_APPLIED]);
    recruitmentCandidate(['job_posting_id' => $posting->id, 'stage' => JobCandidate::STAGE_INTERVIEW]);
    recruitmentCandidate(['job_posting_id' => $other->id, 'stage' => JobCandidate::STAGE_APPLIED]);

    $this->getJson("/api/v1/job-candidates?job_posting_id={$posting->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/job-candidates?stage=interview')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.stage', 'interview');

    $this->getJson("/api/v1/job-candidates?job_posting_id={$posting->id}&stage=applied")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.job_posting_id', $posting->id);
});

it('scopes postings and candidates to the acting user’s company', function () {
    $posting = recruitmentPosting();
    recruitmentCandidate(['job_posting_id' => $posting->id]);

    $otherCompany = Company::factory()->create();
    recruitmentPosting(['company_id' => $otherCompany->id]);
    recruitmentCandidate([
        'company_id' => $otherCompany->id,
        'job_posting_id' => null,
    ]);

    $this->getJson('/api/v1/job-postings')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/job-candidates')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
