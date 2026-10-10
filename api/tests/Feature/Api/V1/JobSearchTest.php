<?php

use App\Models\Company;
use App\Models\JobApplication;
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

function jobPosting(array $attributes = []): JobPosting
{
    return JobPosting::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $attributes));
}

function jobApplication(JobPosting $posting, array $attributes = []): JobApplication
{
    return JobApplication::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'job_posting_id' => $posting->id,
    ], $attributes));
}

function grantJobSearchPermissions(User $user): void
{
    $names = [];

    foreach (['job-openings' => ['view-any', 'view'], 'job-applications' => ['view-any', 'view', 'create', 'update', 'delete']] as $module => $actions) {
        foreach ($actions as $action) {
            $names[] = "{$module}.{$action}";
        }
    }

    foreach ($names as $name) {
        Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::whereIn('name', $names)->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/job-openings')->assertUnauthorized();
    $this->getJson('/api/v1/job-applications')->assertUnauthorized();
});

it('blocks users without the job search permissions', function () {
    grantJobSearchPermissions($this->user);

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/job-openings')->assertForbidden();
    $this->getJson('/api/v1/job-applications')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantJobSearchPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/job-openings')->assertOk();

    $this->putJson('/api/v1/addons/job-search', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/job-openings')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'job-search');
});

it('lists only published job openings', function () {
    grantJobSearchPermissions($this->user);

    jobPosting(['status' => JobPosting::STATUS_DRAFT]);
    jobPosting(['status' => JobPosting::STATUS_PUBLISHED, 'title' => 'Frontend Engineer']);

    $this->getJson('/api/v1/job-openings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Frontend Engineer');
});

it('searches job openings by title and location', function () {
    grantJobSearchPermissions($this->user);

    jobPosting(['status' => JobPosting::STATUS_PUBLISHED, 'title' => 'Frontend Engineer', 'location' => 'Berlin']);
    jobPosting(['status' => JobPosting::STATUS_PUBLISHED, 'title' => 'Accountant', 'location' => 'Lisbon']);

    $this->getJson('/api/v1/job-openings?search=Frontend')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/job-openings?search=Lisbon')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('shows a published posting and 404s a draft', function () {
    grantJobSearchPermissions($this->user);

    $published = jobPosting(['status' => JobPosting::STATUS_PUBLISHED, 'published_at' => now()]);
    $draft = jobPosting(['status' => JobPosting::STATUS_DRAFT]);

    $this->getJson("/api/v1/job-openings/{$published->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $published->id)
        ->assertJsonPath('data.title', $published->title);

    $this->getJson("/api/v1/job-openings/{$draft->id}")->assertNotFound();
});

it('creates an application defaulting its status to received', function () {
    grantJobSearchPermissions($this->user);

    $posting = jobPosting(['status' => JobPosting::STATUS_PUBLISHED]);

    $this->postJson('/api/v1/job-applications', [
        'job_posting_id' => $posting->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+44 20 7946 0000',
        'resume_url' => 'https://example.com/resume.pdf',
        'cover_letter' => 'I would love to join the team.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.name', 'Ada Lovelace')
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.job_posting_id', $posting->id)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects an invalid status', function () {
    grantJobSearchPermissions($this->user);

    $posting = jobPosting(['status' => JobPosting::STATUS_PUBLISHED]);

    $this->postJson('/api/v1/job-applications', [
        'job_posting_id' => $posting->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'status' => 'archived',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('filters applications by posting', function () {
    grantJobSearchPermissions($this->user);

    $posting = jobPosting(['status' => JobPosting::STATUS_PUBLISHED]);
    $other = jobPosting(['status' => JobPosting::STATUS_PUBLISHED]);

    jobApplication($posting, ['name' => 'Ada Lovelace']);
    jobApplication($other, ['name' => 'Alan Turing']);

    $this->getJson("/api/v1/job-applications?job_posting_id={$posting->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.job_posting_id', $posting->id)
        ->assertJsonPath('data.0.name', 'Ada Lovelace');
});

it('scopes job search records to the acting user’s company', function () {
    grantJobSearchPermissions($this->user);

    jobPosting(['status' => JobPosting::STATUS_PUBLISHED]);
    jobPosting([
        'status' => JobPosting::STATUS_PUBLISHED,
        'company_id' => Company::factory()->create()->id,
    ]);

    $this->getJson('/api/v1/job-openings')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    jobApplication(jobPosting(['status' => JobPosting::STATUS_PUBLISHED]));
    jobApplication(
        jobPosting(['company_id' => Company::factory()->create()->id]),
        ['company_id' => Company::factory()->create()->id],
    );

    $this->getJson('/api/v1/job-applications')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
