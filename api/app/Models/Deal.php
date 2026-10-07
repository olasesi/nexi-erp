<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_WON, self::STATUS_LOST];

    protected $table = 'crm_deals';

    protected $fillable = [
        'company_id',
        'lead_id',
        'contact_id',
        'crm_stage_id',
        'assigned_to',
        'title',
        'value',
        'status',
        'close_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'close_date' => 'date',
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
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<CrmStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'crm_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
