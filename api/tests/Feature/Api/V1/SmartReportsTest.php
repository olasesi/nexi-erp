<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\SmartReport;
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

function grantSmartReportPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "smart-reports.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'smart-reports.%')->get());
}

function smartReport(array $overrides = []): SmartReport
{
    return SmartReport::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'created_by' => test()->user->id,
    ], $overrides));
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/smart-reports')->assertUnauthorized();
});

it('blocks users without the smart-reports permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "smart-reports.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/smart-reports')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantSmartReportPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/smart-reports')->assertOk();

    $this->putJson('/api/v1/addons/smart-reports', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/smart-reports')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'smart-reports');
});

it('creates a report and defaults its chart type to table', function () {
    grantSmartReportPermissions($this->user);

    $this->postJson('/api/v1/smart-reports', [
        'name' => 'Lead pipeline',
        'source' => 'leads',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Lead pipeline')
        ->assertJsonPath('data.source', 'leads')
        ->assertJsonPath('data.chart_type', 'table')
        ->assertJsonPath('data.is_shared', false)
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id);
});

it('rejects an unsupported source', function () {
    grantSmartReportPermissions($this->user);

    $this->postJson('/api/v1/smart-reports', [
        'name' => 'Unicorns',
        'source' => 'unicorns',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);
});

it('runs a report and returns the seeded rows', function () {
    grantSmartReportPermissions($this->user);

    $lead = Lead::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Ada Lead',
    ]);

    $report = smartReport([
        'source' => 'leads',
        'columns' => ['name', 'status'],
    ]);

    $this->postJson("/api/v1/smart-reports/{$report->id}/run")
        ->assertOk()
        ->assertJsonPath('columns', ['name', 'status'])
        ->assertJsonFragment(['name' => 'Ada Lead', 'status' => $lead->status]);
});

it('groups rows and returns aggregate totals', function () {
    grantSmartReportPermissions($this->user);

    Lead::factory()->count(2)->create(['company_id' => $this->company->id, 'status' => 'new']);
    Lead::factory()->contacted()->create(['company_id' => $this->company->id]);

    $report = smartReport([
        'source' => 'leads',
        'group_by' => 'status',
        'aggregate' => 'count',
    ]);

    $rows = $this->postJson("/api/v1/smart-reports/{$report->id}/run")
        ->assertOk()
        ->json('rows');

    expect(collect($rows)->firstWhere('status', 'new')['count'])->toEqual(2)
        ->and(collect($rows)->firstWhere('status', 'contacted')['count'])->toEqual(1);
});

it('scopes reports to the acting user’s company', function () {
    grantSmartReportPermissions($this->user);

    smartReport();
    SmartReport::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/smart-reports')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
