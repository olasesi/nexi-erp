<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Database\Factories\HrmDepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int|null $designations_count
 * @property-read int|null $employees_count
 */
class HrmDepartment extends Model
{
    /** @use HasFactory<HrmDepartmentFactory> */
    use CompanyScoped, HasFactory, SoftDeletes;

    protected $table = 'hrm_departments';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'manager_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
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
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return HasMany<HrmDesignation, $this>
     */
    public function designations(): HasMany
    {
        return $this->hasMany(HrmDesignation::class, 'department_id');
    }

    /**
     * @return HasMany<HrmEmployee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(HrmEmployee::class, 'department_id');
    }
}
