<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\TrainingTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingType extends Model
{
    /** @use HasFactory<TrainingTypeFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'training_types';

    protected $fillable = [
        'company_id',
        'name',
        'branch',
        'department',
        'description',
    ];

    /**
     * @return HasMany<Training, $this>
     */
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class, 'training_type_id');
    }
}
