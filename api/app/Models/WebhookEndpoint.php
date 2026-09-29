<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A consumer supplied HTTPS endpoint that receives signed JSON payloads for
 * the events it subscribes to. A null company_id makes the endpoint global.
 *
 * @property-read int|null $company_id
 * @property-read string $name
 * @property-read string $url
 * @property-read string $secret
 * @property-read list<string> $events
 * @property-read bool $is_active
 * @property-read string|null $description
 * @property-read Carbon|null $last_triggered_at
 */
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use CompanyScoped, HasFactory, RecordsActivity;

    protected $fillable = [
        'company_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'description',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @param  Builder<WebhookEndpoint>  $query
     * @return Builder<WebhookEndpoint>
     */
    public function scopeSubscribedTo(Builder $query, string $event): Builder
    {
        return $query->where('is_active', true)->whereJsonContains('events', $event);
    }
}
