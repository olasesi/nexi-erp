<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\JobCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $hired_at
 */
class JobCandidate extends Model
{
    /** @use HasFactory<JobCandidateFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STAGE_APPLIED = 'applied';

    public const STAGE_SCREENING = 'screening';

    public const STAGE_INTERVIEW = 'interview';

    public const STAGE_OFFER = 'offer';

    public const STAGE_HIRED = 'hired';

    public const STAGE_REJECTED = 'rejected';

    public const STAGES = [
        self::STAGE_APPLIED,
        self::STAGE_SCREENING,
        self::STAGE_INTERVIEW,
        self::STAGE_OFFER,
        self::STAGE_HIRED,
        self::STAGE_REJECTED,
    ];

    protected $table = 'job_candidates';

    protected $fillable = [
        'company_id',
        'job_posting_id',
        'name',
        'email',
        'phone',
        'resume_url',
        'source',
        'stage',
        'expected_salary',
        'notes',
        'hired_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_salary' => 'float',
            'hired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
