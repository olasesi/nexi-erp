<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CurrencyRate extends Model
{
    use CompanyScoped, SoftDeletes;

    protected $fillable = [
        'company_id',
        'base_currency',
        'currency',
        'rate',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
