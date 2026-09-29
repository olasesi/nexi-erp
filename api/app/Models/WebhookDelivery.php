<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt log for an event queued against a webhook endpoint. The payload
 * is stored verbatim so failed deliveries can be replayed later.
 *
 * @property-read int|null $company_id
 * @property-read int $webhook_endpoint_id
 * @property-read string $event
 * @property-read string $status
 * @property-read int $attempts
 * @property-read array<string, mixed> $payload
 * @property-read int|null $response_status
 * @property-read string|null $response_body
 * @property-read string|null $last_error
 * @property-read Carbon|null $next_attempt_at
 * @property-read Carbon|null $delivered_at
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use CompanyScoped, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'webhook_endpoint_id',
        'event',
        'status',
        'attempts',
        'payload',
        'response_status',
        'response_body',
        'last_error',
        'next_attempt_at',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @param  Builder<WebhookDelivery>  $query
     * @return Builder<WebhookDelivery>
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function (Builder $query): void {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            });
    }
}
