<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $start_date
 * @property-read Carbon $end_date
 */
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
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

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $table = 'budgets';

    protected $fillable = [
        'company_id',
        'name',
        'period_type',
        'start_date',
        'end_date',
        'status',
        'total_planned',
        'total_actual',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_planned' => 'float',
            'total_actual' => 'float',
        ];
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'budget_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Roll the line items up into the budget header totals. Soft-deleted
     * lines are excluded, so a deleted line stops counting immediately.
     */
    public function recomputeTotals(): self
    {
        $this->total_planned = (float) $this->lines()->sum('planned_amount');
        $this->total_actual = (float) $this->lines()->sum('actual_amount');

        return $this;
    }
}
