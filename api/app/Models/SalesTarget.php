<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\SalesTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $period_start
 * @property-read Carbon|null $period_end
 * @property float $target_amount
 * @property float $achieved_amount
 */
class SalesTarget extends Model
{
    /** @use HasFactory<SalesTargetFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_QUARTERLY = 'quarterly';

    public const PERIOD_YEARLY = 'yearly';

    public const PERIOD_CUSTOM = 'custom';

    public const PERIODS = [
        self::PERIOD_MONTHLY,
        self::PERIOD_QUARTERLY,
        self::PERIOD_YEARLY,
        self::PERIOD_CUSTOM,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ACHIEVED = 'achieved';

    public const STATUS_MISSED = 'missed';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ACHIEVED,
        self::STATUS_MISSED,
    ];

    protected $table = 'sales_targets';

    protected $fillable = [
        'company_id',
        'employee_id',
        'title',
        'period_type',
        'period_start',
        'period_end',
        'target_amount',
        'achieved_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'target_amount' => 'float',
            'achieved_amount' => 'float',
        ];
    }

    public function progressPercentage(): int
    {
        $target = (float) $this->target_amount;

        if ($target <= 0) {
            return 0;
        }

        return (int) max(0, min(100, round((float) $this->achieved_amount / $target * 100)));
    }

    /**
     * @return BelongsTo<HrmEmployee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
