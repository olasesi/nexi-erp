<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\AssetCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCategory extends Model
{
    /** @use HasFactory<AssetCategoryFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'asset_categories';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'depreciation_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'depreciation_rate' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_category_id');
    }
}
