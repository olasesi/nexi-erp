<?php

namespace App\Console\Commands;

use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Console\Command;

/**
 * Retries the webhook deliveries whose backoff has elapsed. With the default
 * "sync" driver nothing reschedules a failed delivery on its own, so this
 * command is what gives a synchronous install its retry behaviour.
 */
class RetryWebhookDeliveries extends Command
{
    protected $signature = 'webhooks:retry
                            {--limit=100 : Maximum deliveries to attempt per run}
                            {--endpoint= : Only retry deliveries of this endpoint}
                            {--dry-run : List the due deliveries without sending them}';

    protected $description = 'Attempt webhook deliveries whose retry backoff has elapsed';

    public function handle(WebhookService $webhooks): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxAttempts = (int) config('webhooks.max_attempts');

        $pending = WebhookDelivery::query()->retryable();

        if ($this->option('endpoint') !== null) {
            $pending->where('webhook_endpoint_id', (int) $this->option('endpoint'));
        }

        $due = (clone $pending)
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stranded = (clone $pending)->where('attempts', '>=', $maxAttempts)->count();

        if ($this->option('dry-run')) {
            foreach ($due as $delivery) {
                $this->line(sprintf(
                    '#%s %s -> endpoint %d (attempt %d of %d)',
                    (string) $delivery->getKey(),
                    $delivery->event,
                    (int) $delivery->webhook_endpoint_id,
                    (int) $delivery->attempts + 1,
                    $maxAttempts
                ));
            }

            $this->info(sprintf('%d delivery/deliveries due, %d stranded.', $due->count(), $stranded));

            return self::SUCCESS;
        }

        $succeeded = 0;
        $failed = 0;

        foreach ($due as $delivery) {
            // This command already owns the retry, so the service must not push
            // another queued job for the same delivery.
            if ($webhooks->attempt($delivery, false)) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        $this->info(sprintf('Attempted %d: %d succeeded, %d failed.', $due->count(), $succeeded, $failed));

        if ($stranded > 0) {
            $this->warn(sprintf('%d pending delivery/deliveries exhausted their attempt budget.', $stranded));
        }

        return self::SUCCESS;
    }
}
