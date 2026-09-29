<?php

namespace App\Console\Commands;

use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * Trims the delivery log so the webhook history cannot grow without bound.
 * Only finished rows are considered: a pending delivery still waiting for its
 * backoff is never touched.
 */
class PruneWebhookDeliveries extends Command
{
    protected $signature = 'webhooks:prune
                            {--days= : Keep deliveries newer than this many days}
                            {--status=succeeded,failed : Comma separated statuses eligible for pruning}
                            {--dry-run : Report how many rows would be removed}';

    protected $description = 'Delete webhook deliveries that finished longer ago than the retention window';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('webhooks.retention_days');

        if ($days < 1) {
            $this->error('The retention window must be at least one day.');

            return self::FAILURE;
        }

        $statuses = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('status'))
        )));

        $cutoff = now()->subDays($days);

        $query = WebhookDelivery::query()
            ->whereIn('status', $statuses)
            ->where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info(sprintf('%d delivery/deliveries older than %d day(s) would be pruned.', $count, $days));

            return self::SUCCESS;
        }

        $query->delete();

        $this->info(sprintf('Pruned %d delivery/deliveries older than %s.', $count, $cutoff->toDateTimeString()));

        return self::SUCCESS;
    }
}
