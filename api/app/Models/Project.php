<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property ProjectStatus $status
 * @property-read Carbon|null $start_date
 * @property-read Carbon|null $end_date
 * @property-read int|null $tasks_count
 * @property-read int|null $milestones_count
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'projects';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'budget',
        'owner_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ProjectTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ProjectStatus::Active->value);
    }

    /**
     * Projects past their end date that are not finished or abandoned.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereDate('end_date', '<', now()->toDateString())
            ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value]);
    }
}
