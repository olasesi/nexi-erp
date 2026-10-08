<?php

namespace App\Models;

use App\Models\Concerns\DispatchesWebhooks;
use Database\Factories\BusinessHealthSnapshotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One scored day per company. Rows are written by `advisor:snapshot`; the
 * live report is computed on demand and only lands here when scheduled.
 *
 * Deliberately without RecordsActivity: these rows are written by a
 * scheduler, and auditing them would make the advisor's own writes count as
 * team activity on the next run.
 *
 * @property-read Carbon $score_date
 */
class BusinessHealthSnapshot extends Model
{
    /** @use HasFactory<BusinessHealthSnapshotFactory> */
    use DispatchesWebhooks, HasFactory;

    protected $table = 'business_health_snapshots';

    protected $fillable = [
        'company_id',
        'score_date',
        'overall_score',
        'financial_score',
        'sales_score',
        'operations_score',
        'team_score',
        'projects_score',
        'signals',
        'alerts',
        'insights',
        'recommendations',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'score_date' => 'date',
            'overall_score' => 'integer',
            'financial_score' => 'integer',
            'sales_score' => 'integer',
            'operations_score' => 'integer',
            'team_score' => 'integer',
            'projects_score' => 'integer',
            'signals' => 'array',
            'alerts' => 'array',
            'insights' => 'array',
            'recommendations' => 'array',
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
     * Newest first, which is how the trend is consumed.
     *
     * @param  Builder<BusinessHealthSnapshot>  $query
     */
    public function scopeRecent(Builder $query): void
    {
        $query->orderByDesc('score_date')->orderByDesc('id');
    }
}
