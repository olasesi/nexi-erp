<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Outbound webhook fan-out: turns model changes into signed HTTP POSTs and
 * records every attempt on a webhook_deliveries row so failures can be
 * inspected and replayed.
 */
class WebhookService
{
    /**
     * Domain level events emitted explicitly by services.
     *
     * @var list<string>
     */
    public const DOMAIN_EVENTS = [
        'invoice.sent',
        'payment.completed',
        'quotation.accepted',
        'quotation.rejected',
        'purchase_receipt.received',
        'stock_transfer.completed',
        'stock_adjustment.posted',
        'reconciliation.matched',
    ];

    /**
     * Model subjects whose lifecycle changes are broadcast.
     *
     * @var list<string>
     */
    public const LIFECYCLE_SUBJECTS = [
        'invoice',
        'payment',
        'contact',
        'product',
        'quotation',
        'sales_order',
        'purchase_order',
        'purchase_receipt',
        'stock_transfer',
        'stock_adjustment',
        'expense',
        'income',
        'bank_transaction',
        'reconciliation',
        'user',
    ];

    /**
     * Lifecycle actions generated for every subject above.
     *
     * @var list<string>
     */
    public const LIFECYCLE_ACTIONS = ['created', 'updated', 'deleted', 'restored'];

    /**
     * Subjects that never generate deliveries, either because they are noise
     * or because broadcasting them would recurse.
     *
     * @var list<string>
     */
    public const IGNORED_SUBJECTS = [
        'audit_log',
        'webhook_endpoint',
        'webhook_delivery',
        'setting',
        'notification',
        'currency_rate',
        'passport_token',
    ];

    /**
     * Attributes stripped from every payload before it leaves the API.
     *
     * @var list<string>
     */
    private const SENSITIVE = ['password', 'remember_token', 'secret', 'password_hash', 'api_key'];

    /**
     * Full catalogue of subscribable events.
     *
     * @return list<string>
     */
    public static function events(): array
    {
        $lifecycle = [];

        foreach (self::LIFECYCLE_SUBJECTS as $subject) {
            foreach (self::LIFECYCLE_ACTIONS as $action) {
                $lifecycle[] = $subject.'.'.$action;
            }
        }

        return array_values(array_unique(array_merge($lifecycle, self::DOMAIN_EVENTS)));
    }

    /**
     * Broadcast a model lifecycle change recorded by the DispatchesWebhooks
     * trait, ignoring subjects that carry no webhook meaning.
     */
    public static function dispatchLifecycle(Model $model, string $action): void
    {
        $subject = Str::snake(class_basename($model));

        if (in_array($subject, self::IGNORED_SUBJECTS, true)) {
            return;
        }

        self::dispatch($subject.'.'.$action, $model);
    }

    /**
     * Fan an event out to every active endpoint subscribed to it. Endpoints
     * without a company receive all events, otherwise only the endpoints of
     * the model's company (and the global ones) are called.
     *
     * @param  array<string, mixed>  $attributes
     * @return list<WebhookDelivery>
     */
    public static function dispatch(string $event, Model $model, array $attributes = []): array
    {
        $event = strtolower(trim($event));

        if (! in_array($event, self::events(), true)) {
            return [];
        }

        $companyId = $model->getAttribute('company_id');
        $companyId = $companyId === null ? null : (int) $companyId;

        $query = WebhookEndpoint::query()->subscribedTo($event);

        if ($companyId !== null) {
            $query->where(function (Builder $query) use ($companyId): void {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }

        $payload = self::payload($event, $model, $attributes);
        $deliveries = [];

        foreach ($query->get() as $endpoint) {
            $deliveries[] = WebhookDelivery::create([
                'company_id' => $endpoint->company_id,
                'webhook_endpoint_id' => $endpoint->getKey(),
                'event' => $event,
                'status' => WebhookDelivery::STATUS_PENDING,
                'attempts' => 0,
                'payload' => $payload,
            ]);

            $endpoint->forceFill(['last_triggered_at' => now()])->save();
        }

        if ($deliveries !== []) {
            self::queueDeliveries($deliveries);
        }

        return $deliveries;
    }

    /**
     * Queue a payload for an endpoint without going through the catalogue, used
     * by the endpoint connectivity test.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function dispatchPayload(WebhookEndpoint $endpoint, string $event, array $payload): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'company_id' => $endpoint->company_id,
            'webhook_endpoint_id' => $endpoint->getKey(),
            'event' => $event,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
            'payload' => $payload,
        ]);

        self::queueDeliveries([$delivery]);

        return $delivery;
    }

    /**
     * Perform a single delivery attempt and record the outcome. Returns true
     * when the endpoint answered with a 2xx response. Set $reschedule to false
     * when the caller owns the retry (the webhooks:retry command) so no extra
     * queued job is pushed for the same delivery.
     */
    public function attempt(WebhookDelivery $delivery, bool $reschedule = true): bool
    {
        $endpoint = $delivery->endpoint;

        if (! $endpoint instanceof WebhookEndpoint) {
            $this->record($delivery, false, null, '', 'The endpoint no longer exists.');

            return false;
        }

        $attempt = $delivery->attempts + 1;
        $timestamp = now()->getTimestamp();
        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $status = null;
        $responseBody = '';
        $error = null;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => (string) config('webhooks.user_agent'),
                'X-Nexi-Event' => $delivery->event,
                'X-Nexi-Delivery' => (string) $delivery->getKey(),
                'X-Nexi-Timestamp' => (string) $timestamp,
                'X-Nexi-Attempt' => (string) $attempt,
                'X-Nexi-Signature' => self::signature($endpoint->secret, $timestamp, $body),
            ])
                ->withBody($body, 'application/json')
                ->timeout((int) config('webhooks.timeout'))
                ->post($endpoint->url);

            $status = $response->status();
            $responseBody = $response->body();
            $ok = $response->successful();
            $error = $ok ? null : 'Endpoint responded with HTTP '.$status.'.';
        } catch (ConnectionException $exception) {
            $ok = false;
            $error = 'Connection failed: '.$exception->getMessage();
        }

        $this->record($delivery, $ok, $status, $responseBody, $error, $attempt, $reschedule);

        return $ok;
    }

    /**
     * HMAC-SHA256 signature of `{timestamp}.{body}` under the endpoint secret.
     */
    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * Verify a signature header, rejecting stale timestamps.
     */
    public static function verifySignature(string $secret, int $timestamp, string $body, string $signature, ?int $tolerance = null): bool
    {
        $tolerance ??= (int) config('webhooks.signature_tolerance');

        if ($tolerance > 0 && abs(now()->getTimestamp() - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(self::signature($secret, $timestamp, $body), $signature);
    }

    /**
     * Envelope sent to subscribers.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function payload(string $event, Model $model, array $attributes = []): array
    {
        return [
            'event' => $event,
            'sent_at' => now()->toISOString(),
            'data' => [
                'id' => $model->getKey(),
                'type' => $model->getMorphClass(),
                'company_id' => $model->getAttribute('company_id') === null
                    ? null
                    : (int) $model->getAttribute('company_id'),
                'attributes' => $attributes !== [] ? $attributes : self::publicAttributes($model),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function publicAttributes(Model $model): array
    {
        $values = $model->attributesToArray();

        return array_filter($values, fn (string $key) => ! in_array($key, self::SENSITIVE, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Hand the deliveries to the configured driver: queued when a worker is
     * available, in-process otherwise.
     *
     * @param  list<WebhookDelivery>  $deliveries
     */
    private static function queueDeliveries(array $deliveries): void
    {
        $queued = (string) config('webhooks.driver') === 'queue';

        foreach ($deliveries as $delivery) {
            $job = new DeliverWebhook($delivery->getKey());

            if ($queued) {
                dispatch($job)->onQueue((string) config('webhooks.queue'))->delay(now());

                continue;
            }

            dispatch_sync($job);
        }
    }

    /**
     * Persist the outcome of an attempt and schedule the next one when the
     * endpoint keeps failing.
     */
    private function record(WebhookDelivery $delivery, bool $ok, ?int $status, string $responseBody, ?string $error, ?int $attempt = null, bool $reschedule = true): void
    {
        $attempt ??= $delivery->attempts + 1;
        $exhausted = $attempt >= (int) config('webhooks.max_attempts');

        $delivery->forceFill([
            'attempts' => $attempt,
            'response_status' => $status,
            'response_body' => mb_strimwidth($responseBody, 0, (int) config('webhooks.response_limit'), ''),
            'last_error' => $ok ? null : $error,
            'status' => $ok
                ? WebhookDelivery::STATUS_SUCCEEDED
                : ($exhausted ? WebhookDelivery::STATUS_FAILED : WebhookDelivery::STATUS_PENDING),
            'delivered_at' => $ok ? now() : null,
            'next_attempt_at' => $ok ? null : now()->addSeconds(self::backoffFor($attempt)),
        ])->save();

        if ($ok) {
            return;
        }

        Log::warning('Webhook delivery failed.', [
            'delivery_id' => $delivery->getKey(),
            'endpoint_id' => $delivery->webhook_endpoint_id,
            'event' => $delivery->event,
            'attempt' => $attempt,
            'error' => $error,
        ]);

        if (! $exhausted && $reschedule && (string) config('webhooks.driver') === 'queue') {
            dispatch(new DeliverWebhook($delivery->getKey()))
                ->onQueue((string) config('webhooks.queue'))
                ->delay($delivery->next_attempt_at);
        }
    }

    /**
     * Seconds to wait before the given attempt is retried.
     */
    private static function backoffFor(int $attempt): int
    {
        $backoff = (array) config('webhooks.backoff');

        if ($backoff === []) {
            return 60;
        }

        $index = max($attempt - 1, 0);
        $value = $backoff[min($index, count($backoff) - 1)] ?? 60;

        return (int) $value;
    }
}
