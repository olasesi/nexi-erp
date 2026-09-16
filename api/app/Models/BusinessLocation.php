<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessLocation extends Model
{
    /** @use HasFactory<BusinessLocation> */
    use CompanyScoped, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'location_id',
        'landmark',
        'city',
        'zip_code',
        'state',
        'country',
        'price_group',
        'invoice_scheme',
        'invoice_layout_for_pos',
        'invoice_layout_for_sale',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
