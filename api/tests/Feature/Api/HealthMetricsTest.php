<?php

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('reports a healthy service with per component latencies', function () {
    $response = $this->getJson('/api/health')->assertOk();

    $response->assertJsonPath('status', HealthCheckService::STATUS_HEALTHY)
        ->assertJsonPath('service', 'nexi-erp')
        ->assertJsonPath('api_version', 'v1')
        ->assertJsonStructure(['status', 'service', 'version', 'api_version', 'timestamp', 'checks'])
        ->assertJsonStructure(['checks' => ['database', 'cache', 'storage', 'queue']]);

    expect($response->json('checks.database.status'))->toBe(HealthCheckService::CHECK_OK)
        ->and($response->json('checks.database.critical'))->toBeTrue()
        ->and($response->json('checks.database.latency_ms'))->toBeGreaterThanOrEqual(0.0)
        ->and($response->json('checks.queue.status'))->toBe(HealthCheckService::CHECK_OK);
});

it('answers 503 when a critical dependency is down', function () {
    // Bind a probe that fails like an unreachable database would.
    $failing = new class extends HealthCheckService
    {
        public function report(): array
        {
            return [
                'status' => self::STATUS_UNHEALTHY,
                'checked_at' => now()->toIso8601String(),
                'checks' => [
                    'database' => [
                        'status' => self::CHECK_ERROR,
                        'critical' => true,
                        'latency_ms' => 0.0,
                        'message' => 'Connection refused',
                    ],
                ],
            ];
        }
    };

    app()->instance(HealthCheckService::class, $failing);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('status', HealthCheckService::STATUS_UNHEALTHY)
        ->assertJsonPath('checks.database.message', 'Connection refused');
});

it('degrades instead of failing when an optional dependency is down', function () {
    $failing = new class extends HealthCheckService
    {
        public function report(): array
        {
            return [
                'status' => self::STATUS_DEGRADED,
                'checked_at' => now()->toIso8601String(),
                'checks' => [
                    'database' => ['status' => self::CHECK_OK, 'critical' => true, 'latency_ms' => 1.5, 'message' => null],
                    'cache' => ['status' => self::CHECK_ERROR, 'critical' => false, 'latency_ms' => 0.2, 'message' => 'Cache store unreachable.'],
                ],
            ];
        }
    };

    app()->instance(HealthCheckService::class, $failing);

    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', HealthCheckService::STATUS_DEGRADED);
});

it('aggregates a critical failure as unhealthy and an optional one as degraded', function () {
    $service = new HealthCheckService;

    $report = $service->report();

    expect($report['status'])->toBe(HealthCheckService::STATUS_HEALTHY)
        ->and($service->components())->toHaveKeys(['database', 'cache', 'storage', 'queue'])
        ->and($service->components()['database'])->toBeTrue();
});

it('exposes the prometheus exposition format', function () {
    User::factory()->count(3)->create();
    WebhookDelivery::factory()->succeeded()->create();

    $response = $this->get('/api/metrics')->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/plain; version=0.0.4')
        ->and($response->getContent())->toContain('nexi_erp_up 1')
        ->and($response->getContent())->toContain('# TYPE nexi_erp_http_requests_total counter')
        ->and($response->getContent())->toContain('nexi_erp_http_responses_total{status_class="2xx"}')
        ->and($response->getContent())->toContain('nexi_erp_http_request_duration_seconds_bucket{le="+Inf"}')
        ->and($response->getContent())->toContain('nexi_erp_health{component="database"} 1')
        ->and($response->getContent())->toContain('nexi_erp_users_total{status="active"} 3')
        ->and($response->getContent())->toContain('nexi_erp_webhook_deliveries_total{status="succeeded"} 1')
        ->and($response->getContent())->toContain('nexi_erp_webhook_deliveries_total{status="pending"} 0');
});

it('counts the requests it serves', function () {
    $this->getJson('/api/health')->assertOk();
    $this->getJson('/api/health')->assertOk();

    $before = (int) Cache::get('nexi_erp_metrics:requests_total', 0);

    $this->getJson('/api/health')->assertOk();

    expect((int) Cache::get('nexi_erp_metrics:requests_total', 0))->toBe($before + 1);
});
