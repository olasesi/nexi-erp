<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Database\Factories\HrmDesignationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int|null $employees_count
 */
class HrmDesignation extends Model
{
    /** @use HasFactory<HrmDesignationFactory> */
    use CompanyScoped, HasFactory, SoftDeletes;

    protected $table = 'hrm_designations';

    protected $fillable = [
        'company_id',
        'department_id',
        'name',
        'code',
        'level',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<HrmDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(HrmDepartment::class, 'department_id');
    }

    /**
     * @return HasMany<HrmEmployee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(HrmEmployee::class, 'designation_id');
    }
}
