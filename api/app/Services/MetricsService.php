<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Cache;

class MetricsService
{
    private const PREFIX = 'nexi_erp_metrics:';

    private const BUCKETS = [0.01, 0.05, 0.1, 0.5, 1.0, 5.0];

    /** Smart Dashboard tabs, so every label set is complete on exposition. */
    private const ANALYTICS_TABS = ['executive', 'financial', 'sales', 'operations'];

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
     * Record an observed Smart Dashboard tab build. Cache hits skip the
     * duration sample: what they cost is the lookup, not the build.
     */
    public function recordAnalytics(string $tab, float $seconds, bool $cached): void
    {
        $this->bump(self::PREFIX."analytics_requests_total:$tab");

        if ($cached) {
            $this->bump(self::PREFIX."analytics_cache_hits_total:$tab");

            return;
        }

        $this->bump(self::PREFIX."analytics_build_duration_sum:$tab", (int) round($seconds * 1000000));
        $this->bump(self::PREFIX."analytics_build_duration_count:$tab");
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

        $lines[] = '# HELP nexi_erp_leads_total CRM leads by funnel status.';
        $lines[] = '# TYPE nexi_erp_leads_total gauge';
        foreach ($this->leadCounts() as $status => $total) {
            $lines[] = sprintf('nexi_erp_leads_total{status="%s"} %d', $status, $total);
        }

        $lines[] = '# HELP nexi_erp_deals_total CRM deals by status.';
        $lines[] = '# TYPE nexi_erp_deals_total gauge';
        foreach ($this->dealCounts() as $status => $total) {
            $lines[] = sprintf('nexi_erp_deals_total{status="%s"} %d', $status, $total);
        }

        $lines[] = '# HELP nexi_erp_pipeline_value_open Combined value of open CRM deals.';
        $lines[] = '# TYPE nexi_erp_pipeline_value_open gauge';
        $lines[] = 'nexi_erp_pipeline_value_open '.number_format($this->openPipelineValue(), 2, '.', '');

        $lines[] = '# HELP nexi_erp_analytics_requests_total Smart Dashboard tab payloads served.';
        $lines[] = '# TYPE nexi_erp_analytics_requests_total counter';
        foreach (self::ANALYTICS_TABS as $tab) {
            $lines[] = sprintf('nexi_erp_analytics_requests_total{tab="%s"} %d', $tab, $this->get(self::PREFIX."analytics_requests_total:$tab"));
        }

        $lines[] = '# HELP nexi_erp_analytics_cache_hits_total Smart Dashboard tab payloads served from cache.';
        $lines[] = '# TYPE nexi_erp_analytics_cache_hits_total counter';
        foreach (self::ANALYTICS_TABS as $tab) {
            $lines[] = sprintf('nexi_erp_analytics_cache_hits_total{tab="%s"} %d', $tab, $this->get(self::PREFIX."analytics_cache_hits_total:$tab"));
        }

        $lines[] = '# HELP nexi_erp_analytics_build_duration_seconds Time spent building Smart Dashboard tab payloads.';
        $lines[] = '# TYPE nexi_erp_analytics_build_duration_seconds summary';
        foreach (self::ANALYTICS_TABS as $tab) {
            $sum = $this->get(self::PREFIX."analytics_build_duration_sum:$tab");
            $count = $this->get(self::PREFIX."analytics_build_duration_count:$tab");
            $lines[] = sprintf('nexi_erp_analytics_build_duration_seconds_sum{tab="%s"} %s', $tab, number_format($sum / 1000000, 6, '.', ''));
            $lines[] = sprintf('nexi_erp_analytics_build_duration_seconds_count{tab="%s"} %d', $tab, $count);
        }

        $lines[] = '# HELP nexi_erp_health Whether each backend component is reachable.';
        $lines[] = '# TYPE nexi_erp_health gauge';
        foreach ($this->health->components() as $component => $healthy) {
            $lines[] = "nexi_erp_health{component=\"$component\"} ".($healthy ? '1' : '0');
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * CRM leads per funnel status, always emitting every status so a scraper
     * never has to treat a missing label as zero. Aggregates run without an
     * authenticated user, so the company scope is skipped on purpose: these
     * gauges describe the whole installation, like nexi_erp_users_total.
     *
     * @return array<string, int>
     */
    private function leadCounts(): array
    {
        $counts = array_fill_keys(Lead::STATUSES, 0);

        foreach (Lead::query()->selectRaw('status, count(*) as total')->groupBy('status')->get() as $row) {
            $status = (string) $row->getAttribute('status');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row->getAttribute('total');
            }
        }

        return $counts;
    }

    /**
     * CRM deals per status, zero-filled the same way as the lead gauge.
     *
     * @return array<string, int>
     */
    private function dealCounts(): array
    {
        $counts = array_fill_keys(Deal::STATUSES, 0);

        foreach (Deal::query()->selectRaw('status, count(*) as total')->groupBy('status')->get() as $row) {
            $status = (string) $row->getAttribute('status');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row->getAttribute('total');
            }
        }

        return $counts;
    }

    /**
     * Combined value of every deal still in play, across all companies.
     */
    private function openPipelineValue(): float
    {
        return (float) Deal::query()->where('status', Deal::STATUS_OPEN)->sum('value');
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
