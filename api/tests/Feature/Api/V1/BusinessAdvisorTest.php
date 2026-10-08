<?php

use App\Models\AuditLog;
use App\Models\BusinessHealthSnapshot;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use App\Services\AddonService;
use App\Services\BusinessHealthService;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);

    foreach (['view-any', 'view'] as $action) {
        $this->user->givePermissionTo(
            Permission::firstOrCreate([
                'name' => "business-advisor.{$action}",
                'guard_name' => PermissionGuard::name(),
            ])
        );
    }

    $this->user->givePermissionTo(
        Permission::firstOrCreate([
            'name' => 'addons.update',
            'guard_name' => PermissionGuard::name(),
        ])
    );
});

function advisorProduct(Company $company, int $stock, int $min = 5): Product
{
    return Product::factory()->create([
        'company_id' => $company->id,
        'type' => 'product',
        'is_active' => true,
        'stock_quantity' => $stock,
        'min_stock_level' => $min,
    ]);
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/business-advisor/report')->assertUnauthorized();
});

it('blocks a user without the business-advisor permissions', function () {
    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/business-advisor/report')->assertForbidden();
    $this->getJson('/api/v1/business-advisor/snapshots')->assertForbidden();
});

it('returns a scorecard with every category and a note where there is no data', function () {
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'sent',
        'type' => 'invoice',
        'issue_date' => now()->subDays(5)->toDateString(),
        'due_date' => now()->addDays(10)->toDateString(),
        'total' => 1000,
        'balance_due' => 1000,
    ]);
    advisorProduct($this->company, 40);

    $response = $this->getJson('/api/v1/business-advisor/report')->assertOk();

    expect(array_keys($response->json('categories')))
        ->toBe(['financial', 'sales', 'operations', 'team', 'projects'])
        ->and($response->json('categories.projects.score'))->toBeNull()
        ->and($response->json('categories.projects.note'))->toContain('excluded from the score')
        ->and($response->json('categories.financial.score'))->toBeInt()
        ->and($response->json('coverage.total'))->toBe(4)
        ->and($response->json('coverage.scored'))->toBe(4)
        ->and($response->json('overall_score'))->toBeInt()
        ->and($response->json('generated_by'))->toBe('rules');

    expect($response->json('alerts'))->toBeArray()
        ->and($response->json('insights'))->toBeArray()
        ->and($response->json('recommendations'))->toBeArray()
        ->and($response->json('trend.points'))->toBeArray()
        ->and($response->json('trend.delta'))->toBeNull();
});

it('withholds the overall score until at least two categories have data', function () {
    $response = $this->getJson('/api/v1/business-advisor/report')->assertOk();

    expect($response->json('overall_score'))->toBeNull()
        ->and($response->json('coverage.scored'))->toBe(1)
        ->and($response->json('categories.team.score'))->toBeInt()
        ->and($response->json('categories.financial.score'))->toBeNull()
        ->and($response->json('categories.financial.note'))->not->toBeNull();
});

it('flags out of stock products as a critical alert and recommends a restock', function () {
    advisorProduct($this->company, 0);
    advisorProduct($this->company, 2, 10);

    $response = $this->getJson('/api/v1/business-advisor/report')->assertOk();

    $keys = collect($response->json('alerts'))->pluck('key')->all();

    expect($keys)->toContain('stock_out', 'stock_low');

    $outOfStock = collect($response->json('alerts'))->firstWhere('key', 'stock_out');

    expect($outOfStock['severity'])->toBe('critical')
        ->and($outOfStock['domain'])->toBe('operations');

    $restock = collect($response->json('recommendations'))
        ->first(fn (array $item): bool => str_contains($item['title'], 'Restock'));

    expect($restock)->not->toBeNull()
        ->and($restock['domain'])->toBe('operations');
});

it('flags overdue invoices and recommends collecting them', function () {
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'sent',
        'type' => 'invoice',
        'issue_date' => now()->subDays(20)->toDateString(),
        'due_date' => now()->subDays(5)->toDateString(),
        'total' => 500,
        'balance_due' => 500,
    ]);

    $response = $this->getJson('/api/v1/business-advisor/report')->assertOk();

    $overdue = collect($response->json('alerts'))->firstWhere('key', 'overdue_invoices');

    expect($overdue)->not->toBeNull()
        ->and($overdue['domain'])->toBe('financial')
        ->and($overdue['detail'])->toContain('500.00');

    expect(collect($response->json('recommendations'))->pluck('title'))
        ->toContain('Collect what is already owed');
});

it('reports only the quiet-team alert for a company with no activity', function () {
    AuditLog::query()->delete();

    $response = $this->getJson('/api/v1/business-advisor/report')->assertOk();

    expect(collect($response->json('alerts'))->pluck('key')->all())->toBe(['no_activity'])
        ->and($response->json('recommendations'))->toBeArray();
});

it('scores insights from real numbers rather than placeholders', function () {
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'paid',
        'type' => 'invoice',
        'issue_date' => now()->subDays(3)->toDateString(),
        'total' => 2000,
        'balance_due' => 0,
    ]);

    $text = collect($this->getJson('/api/v1/business-advisor/report')->json('insights'))
        ->pluck('text')
        ->implode(' ');

    expect($text)->toContain('2,000.00')
        ->and($text)->not->toContain('Lorem')
        ->and($text)->not->toContain('placeholder');
});

it('records a daily snapshot and notifies the company once about critical alerts', function () {
    advisorProduct($this->company, 0);

    $service = app(BusinessHealthService::class);

    $snapshot = $service->record($this->company->id);

    expect($snapshot->score_date->toDateString())->toBe(now()->toDateString())
        ->and(BusinessHealthSnapshot::count())->toBe(1)
        ->and(Notification::where('type', 'business_advisor')->count())->toBe(1);

    $service->record($this->company->id);

    expect(BusinessHealthSnapshot::count())->toBe(1)
        ->and(Notification::where('type', 'business_advisor')->count())->toBe(1);
});

it('exposes stored snapshots for the trend', function () {
    advisorProduct($this->company, 30);
    app(BusinessHealthService::class)->record($this->company->id);

    $response = $this->getJson('/api/v1/business-advisor/snapshots')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.company_id', $this->company->id);

    expect($response->json('data.0.overall_score'))->toBeInt()
        ->and($response->json('data.0.score_date'))->toBe(now()->toDateString());
});

it('computes the trend delta against the previous stored score', function () {
    advisorProduct($this->company, 30);
    app(BusinessHealthService::class)->record($this->company->id);

    BusinessHealthSnapshot::create([
        'company_id' => $this->company->id,
        'score_date' => now()->subDay()->toDateString(),
        'overall_score' => 40,
    ]);

    $response = $this->getJson('/api/v1/business-advisor/report')->assertOk();

    expect($response->json('trend.points'))->toHaveCount(2)
        ->and($response->json('trend.delta'))
        ->toBe($response->json('overall_score') - 40);
});

it('hides the add-on behind the company toggle', function () {
    $this->putJson('/api/v1/addons/business-advisor', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/business-advisor/report')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled');
});

it('scores every company when the nightly command runs', function () {
    $other = Company::factory()->create();

    $this->artisan('advisor:snapshot')->assertSuccessful();

    expect(BusinessHealthSnapshot::whereIn('company_id', [$this->company->id, $other->id])->count())->toBe(2);
});

it('leaves a company that switched the add-on off unscored', function () {
    $other = Company::factory()->create();
    app(AddonService::class)->set('business-advisor', false, $other->id);

    $this->artisan('advisor:snapshot')->assertSuccessful();

    expect(BusinessHealthSnapshot::whereIn('company_id', [$this->company->id, $other->id])->pluck('company_id')->all())
        ->toBe([$this->company->id]);
});
