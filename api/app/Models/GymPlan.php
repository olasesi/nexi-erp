<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\GymPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GymPlan extends Model
{
    /** @use HasFactory<GymPlanFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_QUARTERLY = 'quarterly';

    public const PERIOD_YEARLY = 'yearly';

    public const PERIODS = [
        self::PERIOD_MONTHLY,
        self::PERIOD_QUARTERLY,
        self::PERIOD_YEARLY,
    ];

    protected $table = 'gym_plans';

    protected $fillable = [
        'company_id',
        'name',
        'billing_period',
        'price',
        'join_fee',
        'max_monthly_visits',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'join_fee' => 'float',
            'max_monthly_visits' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GymMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(GymMember::class, 'plan_id');
    }

    /**
     * @param  Builder<GymPlan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
