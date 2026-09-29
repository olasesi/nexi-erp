<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Performs a single webhook delivery attempt. Retries are scheduled by
 * WebhookService, so the job itself never rethrows.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(WebhookService $webhooks): void
    {
        $delivery = WebhookDelivery::withoutGlobalScopes()->find($this->deliveryId);

        if (! $delivery instanceof WebhookDelivery || $delivery->status === WebhookDelivery::STATUS_SUCCEEDED) {
            return;
        }

        $webhooks->attempt($delivery);
    }
}
