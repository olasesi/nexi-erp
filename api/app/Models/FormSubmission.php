<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_REVIEWED,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'form_submissions';

    protected $fillable = [
        'company_id',
        'form_id',
        'submitted_by',
        'status',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
