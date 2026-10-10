<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPES = [
        self::TYPE_INCOME,
        self::TYPE_EXPENSE,
    ];

    protected $table = 'budget_lines';

    protected $fillable = [
        'company_id',
        'budget_id',
        'category',
        'type',
        'planned_amount',
        'actual_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'float',
            'actual_amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }
}
