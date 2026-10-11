<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-company connection state for a third-party provider. Credentials are
 * stored in the matching `business-settings` group; this row only tracks
 * whether the provider is configured, connected and when it was last tested.
 *
 * @property-read Carbon|null $connected_at
 * @property-read Carbon|null $last_tested_at
 */
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity;

    public const STATUS_CONFIGURATION_REQUIRED = 'configuration_required';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISABLED = 'disabled';

    public const STATUSES = [
        self::STATUS_CONFIGURATION_REQUIRED,
        self::STATUS_CONNECTED,
        self::STATUS_DISABLED,
    ];

    public const TEST_SUCCESS = 'success';

    public const TEST_FAILED = 'failed';

    protected $table = 'integrations';

    protected $fillable = [
        'company_id',
        'provider',
        'status',
        'is_connected',
        'connected_at',
        'last_tested_at',
        'last_test_status',
        'meta',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_connected' => 'boolean',
            'connected_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isConnected(): bool
    {
        return $this->is_connected === true;
    }
}
