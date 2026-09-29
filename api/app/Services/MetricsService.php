<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Cache;

class MetricsService
{
    private const PREFIX = 'nexi_erp_metrics:';

    private const BUCKETS = [0.01, 0.05, 0.1, 0.5, 1.0, 5.0];

    /**
     * TTL (seconds) for counters seeded on demand, far past any window in
     * which an increment would ever matter.
     */
    private const COUNTER_TTL = 31536000;

    public function __construct(
        private HealthCheckService $health
    ) {}

    /**
     * Record an observed HTTP request. Counters live in the configured cache
     * store so values are shared across PHP-FPM workers and (with a shared
     * backend) across replicas.
     */
    public function record(float $seconds, int $status): void
    {
        $this->bump(self::PREFIX.'requests_total');
        $this->bump(self::PREFIX.'request_duration_sum', (int) round($seconds * 1000000));
        $this->bump(self::PREFIX.'request_duration_count');
        $this->bump(self::PREFIX.'status_class:'.$this->statusClass($status));

        if ($status >= 500) {
            $this->bump(self::PREFIX.'request_errors_total');
        } elseif ($status >= 400) {
            $this->bump(self::PREFIX.'request_client_errors_total');
        }

        foreach (self::BUCKETS as $index => $upper) {
            if ($seconds <= $upper) {
                $this->bump(self::PREFIX."buckets:$index");
            }
        }
    }

    /**
     * Record an observed authentication result.
     */
    public function recordAuth(bool $success): void
    {
        if ($success) {
            $this->bump(self::PREFIX.'auth_logins_total');
        } else {
            $this->bump(self::PREFIX.'auth_login_failures_total');
        }
    }

    /**
     * Record a registration (staff or customer portal account).
     */
    public function recordRegistration(): void
    {
        $this->bump(self::PREFIX.'auth_registrations_total');
    }

    /**
     * Record an in-app notification being created.
     */
    public function recordNotification(): void
    {
        $this->bump(self::PREFIX.'notifications_created_total');
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

        $lines[] = '# HELP nexi_erp_http_request_client_errors_total HTTP requests that completed with status 4xx.';
        $lines[] = '# TYPE nexi_erp_http_request_client_errors_total counter';
        $lines[] = 'nexi_erp_http_request_client_errors_total '.$this->get(self::PREFIX.'request_client_errors_total');

        $lines[] = '# HELP nexi_erp_http_responses_total HTTP requests by response status class.';
        $lines[] = '# TYPE nexi_erp_http_responses_total counter';
        foreach (['1', '2', '3', '4', '5'] as $class) {
            $lines[] = sprintf('nexi_erp_http_responses_total{status_class="%sxx"} %d', $class, $this->get(self::PREFIX.'status_class:'.$class));
        }

        $lines[] = '# HELP nexi_erp_http_request_duration_seconds HTTP request latency distribution.';
        $lines[] = '# TYPE nexi_erp_http_request_duration_seconds histogram';
        foreach (self::BUCKETS as $index => $upper) {
            $lines[] = 'nexi_erp_http_request_duration_seconds_bucket{le="'.number_format($upper, 3).'"} '.$this->get(self::PREFIX."buckets:$index");
        }
        $lines[] = 'nexi_erp_http_request_duration_seconds_bucket{le="+Inf"} '.$this->get(self::PREFIX.'request_duration_count');
        $lines[] = 'nexi_erp_http_request_duration_seconds_sum '.number_format($this->get(self::PREFIX.'request_duration_sum') / 1000000, 6);
        $lines[] = 'nexi_erp_http_request_duration_seconds_count '.$this->get(self::PREFIX.'request_duration_count');

        $lines[] = '# HELP nexi_erp_auth_logins_total Successful password-grant logins.';
        $lines[] = '# TYPE nexi_erp_auth_logins_total counter';
        $lines[] = 'nexi_erp_auth_logins_total '.$this->get(self::PREFIX.'auth_logins_total');

        $lines[] = '# HELP nexi_erp_auth_login_failures_total Failed password-grant logins.';
        $lines[] = '# TYPE nexi_erp_auth_login_failures_total counter';
        $lines[] = 'nexi_erp_auth_login_failures_total '.$this->get(self::PREFIX.'auth_login_failures_total');

        $lines[] = '# HELP nexi_erp_auth_registrations_total Accounts created (staff or customer portal).';
        $lines[] = '# TYPE nexi_erp_auth_registrations_total counter';
        $lines[] = 'nexi_erp_auth_registrations_total '.$this->get(self::PREFIX.'auth_registrations_total');

        $lines[] = '# HELP nexi_erp_users_total User accounts by active status.';
        $lines[] = '# TYPE nexi_erp_users_total gauge';
        $users = User::query()->selectRaw('is_active, count(*) as total')->groupBy('is_active')->get();
        $active = $users->where('is_active', true)->sum('total');
        $inactive = $users->where('is_active', false)->sum('total');
        $lines[] = "nexi_erp_users_total{status=\"active\"} $active";
        $lines[] = "nexi_erp_users_total{status=\"inactive\"} $inactive";

        $lines[] = '# HELP nexi_erp_notifications_created_total In-app notifications created.';
        $lines[] = '# TYPE nexi_erp_notifications_created_total counter';
        $lines[] = 'nexi_erp_notifications_created_total '.$this->get(self::PREFIX.'notifications_created_total');

        $lines[] = '# HELP nexi_erp_notifications_total In-app notifications by read status.';
        $lines[] = '# TYPE nexi_erp_notifications_total gauge';
        $unreadNotifications = Notification::whereNull('read_at')->count();
        $readNotifications = Notification::whereNotNull('read_at')->count();
        $lines[] = "nexi_erp_notifications_total{status=\"unread\"} $unreadNotifications";
        $lines[] = "nexi_erp_notifications_total{status=\"read\"} $readNotifications";

        $lines[] = '# HELP nexi_erp_webhook_deliveries_total Outbound webhook deliveries by status.';
        $lines[] = '# TYPE nexi_erp_webhook_deliveries_total gauge';
        foreach ($this->deliveryCounts() as $status => $total) {
            $lines[] = sprintf('nexi_erp_webhook_deliveries_total{status="%s"} %d', $status, $total);
        }

        $lines[] = '# HELP nexi_erp_health Whether each backend component is reachable.';
        $lines[] = '# TYPE nexi_erp_health gauge';
        foreach ($this->health->components() as $component => $healthy) {
            $lines[] = "nexi_erp_health{component=\"$component\"} ".($healthy ? '1' : '0');
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * First letter status class, 5xx for anything unexpected.
     */
    private function statusClass(int $status): string
    {
        $class = intdiv($status, 100);

        return (string) ($class >= 1 && $class <= 5 ? $class : 5);
    }

    /**
     * Delivery rows per status, always including the statuses with no rows.
     *
     * @return array<string, int>
     */
    private function deliveryCounts(): array
    {
        $counts = [
            WebhookDelivery::STATUS_PENDING => 0,
            WebhookDelivery::STATUS_SUCCEEDED => 0,
            WebhookDelivery::STATUS_FAILED => 0,
        ];

        foreach (WebhookDelivery::query()->selectRaw('status, count(*) as total')->groupBy('status')->get() as $row) {
            $status = (string) $row->getAttribute('status');
            $counts[$status] = (int) $row->getAttribute('total');
        }

        return $counts;
    }

    /**
     * Increment a counter, seeding it first when the underlying cache store
     * (notably the database driver) refuses to increment a missing key.
     */
    private function bump(string $key, int $value = 1): void
    {
        if (! Cache::increment($key, $value)) {
            Cache::add($key, $value, self::COUNTER_TTL);
        }
    }

    private function get(string $key): int
    {
        return (int) Cache::get($key, 0);
    }
}
