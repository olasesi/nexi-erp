<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $start_date
 * @property-read Carbon|null $end_date
 */
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const TYPE_OBJECTIVE = 'objective';

    public const TYPE_OKR = 'okr';

    public const TYPE_KPI = 'kpi';

    public const TYPES = [
        self::TYPE_OBJECTIVE,
        self::TYPE_OKR,
        self::TYPE_KPI,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'goals';

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'type',
        'category',
        'owner_id',
        'start_date',
        'end_date',
        'status',
        'progress',
        'target_value',
        'current_value',
        'unit',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'target_value' => 'float',
            'current_value' => 'float',
        ];
    }

    /**
     * @return HasMany<GoalKeyResult, $this>
     */
    public function keyResults(): HasMany
    {
        return $this->hasMany(GoalKeyResult::class, 'goal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
