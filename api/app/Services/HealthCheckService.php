<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Dependency probes behind /api/health and the nexi_erp_health gauge. The
 * database is the only critical component: when it is gone the API cannot
 * serve anything, while a broken cache, storage disk or queue only degrades
 * it.
 */
class HealthCheckService
{
    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_UNHEALTHY = 'unhealthy';

    public const CHECK_OK = 'ok';

    public const CHECK_ERROR = 'error';

    /**
     * Run every probe and summarise the result.
     *
     * @return array{status: string, checked_at: string, checks: array<string, array{status: string, critical: bool, latency_ms: float, message: string|null}>}
     */
    public function report(): array
    {
        $checks = [
            'database' => $this->measure(fn (): bool => DB::select('select 1') !== [], critical: true),
            'cache' => $this->measure(function (): bool {
                // Prove the store both reads and writes without writing on
                // every poll: the probe key is seeded at most once a run.
                $store = Cache::store();

                return $store->add('nexi-health-check', 1, 60) || $store->get('nexi-health-check') !== null;
            }),
            'storage' => $this->measure(fn (): bool => Storage::disk('local')->exists('/')),
            'queue' => $this->measure(fn (): bool => $this->queueIsReachable()),
        ];

        return [
            'status' => $this->aggregate($checks),
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * One boolean per component, used for the Prometheus health gauge.
     *
     * @return array<string, bool>
     */
    public function components(): array
    {
        $components = [];

        foreach ($this->report()['checks'] as $component => $check) {
            $components[$component] = $check['status'] === self::CHECK_OK;
        }

        return $components;
    }

    /**
     * Worst overall status: a failed critical check takes the service down, a
     * failed optional one only degrades it.
     *
     * @param  array<string, array{status: string, critical: bool, latency_ms: float, message: string|null}>  $checks
     */
    private function aggregate(array $checks): string
    {
        $failed = array_filter($checks, fn (array $check): bool => $check['status'] !== self::CHECK_OK);

        if ($failed === []) {
            return self::STATUS_HEALTHY;
        }

        foreach ($failed as $check) {
            if ($check['critical']) {
                return self::STATUS_UNHEALTHY;
            }
        }

        return self::STATUS_DEGRADED;
    }

    /**
     * Time a probe and classify the outcome.
     *
     * @return array{status: string, critical: bool, latency_ms: float, message: string|null}
     */
    private function measure(callable $probe, bool $critical = false): array
    {
        $start = hrtime(true);

        try {
            $ok = (bool) $probe();
            $message = $ok ? null : 'The probe returned an unexpected result.';
        } catch (Throwable $exception) {
            $ok = false;
            $message = $exception->getMessage();
        }

        $latency = (hrtime(true) - $start) / 1000000;

        if ($ok) {
            return ['status' => self::CHECK_OK, 'critical' => $critical, 'latency_ms' => round($latency, 3), 'message' => null];
        }

        return [
            'status' => self::CHECK_ERROR,
            'critical' => $critical,
            'latency_ms' => round($latency, 3),
            'message' => $message,
        ];
    }

    /**
     * Only drivers that can be probed cheaply are contacted; sync and the
     * cloud queues report themselves as reachable.
     */
    private function queueIsReachable(): bool
    {
        $connection = (string) config('queue.default');

        return match ($connection) {
            'redis' => (bool) Redis::connection()->ping(),
            'database' => DB::table((string) config('queue.connections.database.table', 'jobs'))->limit(1)->exists(),
            default => true,
        };
    }
}
