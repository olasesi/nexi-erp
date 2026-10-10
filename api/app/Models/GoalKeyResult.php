<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\GoalKeyResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoalKeyResult extends Model
{
    /** @use HasFactory<GoalKeyResultFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_AT_RISK = 'at_risk';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_AT_RISK,
        self::STATUS_COMPLETED,
    ];

    protected $table = 'goal_key_results';

    protected $fillable = [
        'company_id',
        'goal_id',
        'title',
        'target_value',
        'current_value',
        'unit',
        'progress',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'target_value' => 'float',
            'current_value' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }
}
