<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\PerformanceIndicatorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceIndicator extends Model
{
    /** @use HasFactory<PerformanceIndicatorFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'performance_indicators';

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'weight',
        'target_value',
        'unit',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'target_value' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
