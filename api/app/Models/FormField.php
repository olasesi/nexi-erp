<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const TYPES = [
        'text',
        'textarea',
        'email',
        'number',
        'date',
        'select',
        'checkbox',
        'radio',
        'file',
        'tel',
        'url',
    ];

    protected $table = 'form_fields';

    protected $fillable = [
        'company_id',
        'form_id',
        'label',
        'name',
        'type',
        'placeholder',
        'options',
        'is_required',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
