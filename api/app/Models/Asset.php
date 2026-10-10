<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $purchase_date
 */
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const CONDITION_NEW = 'new';

    public const CONDITION_GOOD = 'good';

    public const CONDITION_FAIR = 'fair';

    public const CONDITION_POOR = 'poor';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITIONS = [
        self::CONDITION_NEW,
        self::CONDITION_GOOD,
        self::CONDITION_FAIR,
        self::CONDITION_POOR,
        self::CONDITION_DAMAGED,
    ];

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_LOST = 'lost';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_ASSIGNED,
        self::STATUS_MAINTENANCE,
        self::STATUS_RETIRED,
        self::STATUS_LOST,
    ];

    protected $table = 'assets';

    protected $fillable = [
        'company_id',
        'asset_category_id',
        'asset_tag',
        'name',
        'serial_number',
        'purchase_date',
        'purchase_cost',
        'current_value',
        'condition',
        'status',
        'location',
        'assigned_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'float',
            'current_value' => 'float',
        ];
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * @return HasMany<AssetAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
