<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnit extends Model
{
    /** @use HasFactory<ProductUnitFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const OPERATOR_MULTIPLY = 'multiply';

    public const OPERATOR_DIVIDE = 'divide';

    public const OPERATORS = [
        self::OPERATOR_MULTIPLY,
        self::OPERATOR_DIVIDE,
    ];

    protected $table = 'product_units';

    protected $fillable = [
        'company_id',
        'name',
        'short_name',
        'base_unit',
        'operator',
        'operation_value',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'operation_value' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertsToBase(float $value): float
    {
        if ($this->operator === self::OPERATOR_DIVIDE) {
            if ($this->operation_value === 0.0) {
                return $value;
            }

            return $value / $this->operation_value;
        }

        return $value * $this->operation_value;
    }
}
