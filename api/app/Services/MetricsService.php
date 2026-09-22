<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MetricsService
{
    private const PREFIX = 'nexi_erp_metrics:';

    private const BUCKETS = [0.01, 0.05, 0.1, 0.5, 1.0, 5.0];

    /**
     * Record an observed HTTP request. Counters live in the configured cache
     * store so values are shared across PHP-FPM workers and (with a shared
     * backend) across replicas.
     */
    public function record(float $seconds, int $status): void
    {
        Cache::increment(self::PREFIX.'requests_total', 1);
        Cache::increment(self::PREFIX.'request_duration_sum', (int) round($seconds * 1000000));
        Cache::increment(self::PREFIX.'request_duration_count', 1);

        if ($status >= 500) {
            Cache::increment(self::PREFIX.'request_errors_total', 1);
        }

        foreach (self::BUCKETS as $index => $upper) {
            if ($seconds <= $upper) {
                Cache::increment(self::PREFIX."buckets:$index", 1);
            }
        }
    }

    /**
     * Prometheus text exposition format (v0.0.4).
     */
    public function exposition(): string
    {
        $lines = [];

        $lines[] = '# HELP nexi_erp_up Whether the API process is up and serving requests.';
        $lines[] = '# TYPE nexi_erp_up gauge';
        $lines[] = 'nexi_erp_up 1';

        $lines[] = '# HELP nexi_erp_uptime_seconds Seconds since the process started.';
        $lines[] = '# TYPE nexi_erp_uptime_seconds gauge';
        $lines[] = 'nexi_erp_uptime_seconds '.(defined('LARAVEL_START') ? (int) round(microtime(true) - LARAVEL_START) : 0);

        $version = config('app.version', '1.0.0');
        $lines[] = '# HELP nexi_erp_build_info Build metadata for the running application.';
        $lines[] = '# TYPE nexi_erp_build_info gauge';
        $lines[] = "nexi_erp_build_info{version=\"$version\"} 1";

        $lines[] = '# HELP nexi_erp_http_requests_total Total HTTP requests observed.';
        $lines[] = '# TYPE nexi_erp_http_requests_total counter';
        $lines[] = 'nexi_erp_http_requests_total '.$this->get(self::PREFIX.'requests_total');

        $lines[] = '# HELP nexi_erp_http_request_errors_total HTTP requests that completed with status >= 500.';
        $lines[] = '# TYPE nexi_erp_http_request_errors_total counter';
        $lines[] = 'nexi_erp_http_request_errors_total '.$this->get(self::PREFIX.'request_errors_total');

        $lines[] = '# HELP nexi_erp_http_request_duration_seconds HTTP request latency distribution.';
        $lines[] = '# TYPE nexi_erp_http_request_duration_seconds histogram';
        foreach (self::BUCKETS as $index => $upper) {
            $lines[] = 'nexi_erp_http_request_duration_seconds_bucket{le="'.number_format($upper, 3).'"} '.$this->get(self::PREFIX."buckets:$index");
        }
        $lines[] = 'nexi_erp_http_request_duration_seconds_bucket{le="+Inf"} '.$this->get(self::PREFIX.'request_duration_count');
        $lines[] = 'nexi_erp_http_request_duration_seconds_sum '.number_format($this->get(self::PREFIX.'request_duration_sum') / 1000000, 6);
        $lines[] = 'nexi_erp_http_request_duration_seconds_count '.$this->get(self::PREFIX.'request_duration_count');

        $lines[] = '# HELP nexi_erp_health Whether each backend component is reachable.';
        $lines[] = '# TYPE nexi_erp_health gauge';
        foreach (self::healthChecks() as $component => $healthy) {
            $lines[] = "nexi_erp_health{component=\"$component\"} ".($healthy ? '1' : '0');
        }

        return implode("\n", $lines)."\n";
    }

    private function get(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    /**
     * @return array<string, bool>
     */
    private static function healthChecks(): array
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['database'] = false;
        }

        try {
            Cache::store()->has('health-check');
            $checks['cache'] = true;
        } catch (\Throwable) {
            $checks['cache'] = false;
        }

        try {
            Storage::disk('local')->exists('/');
            $checks['storage'] = true;
        } catch (\Throwable) {
            $checks['storage'] = false;
        }

        return $checks;
    }
}
