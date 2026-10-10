<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\SmartReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved report definition over one of the company's live data sources.
 *
 * @property array<int, string>|null $columns
 * @property array<mixed>|null $filters
 */
class SmartReport extends Model
{
    /** @use HasFactory<SmartReportFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    /**
     * @return list<string>
     */
    public const SOURCES = ['leads', 'deals', 'invoices', 'projects', 'tickets', 'expenses', 'contacts', 'products'];

    /**
     * @return list<string>
     */
    public const AGGREGATES = ['count', 'sum', 'avg', 'min', 'max'];

    /**
     * @return list<string>
     */
    public const CHART_TYPES = ['table', 'bar', 'line', 'pie'];

    protected $table = 'smart_reports';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'source',
        'columns',
        'filters',
        'group_by',
        'aggregate',
        'aggregate_field',
        'chart_type',
        'is_shared',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
            'is_shared' => 'boolean',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
