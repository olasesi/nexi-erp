<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $published_at
 * @property Carbon|null $closing_date
 */
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CLOSED,
    ];

    public const EMPLOYMENT_TYPES = [
        'full_time',
        'part_time',
        'contract',
        'internship',
        'temporary',
    ];

    protected $table = 'job_postings';

    protected $fillable = [
        'company_id',
        'title',
        'department',
        'location',
        'employment_type',
        'description',
        'requirements',
        'salary_min',
        'salary_max',
        'openings',
        'status',
        'published_at',
        'closing_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'salary_min' => 'float',
            'salary_max' => 'float',
            'openings' => 'integer',
            'published_at' => 'datetime',
            'closing_date' => 'date',
        ];
    }

    /**
     * @return HasMany<JobCandidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(JobCandidate::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
