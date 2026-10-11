<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\DebitNoteItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read float $quantity
 * @property-read float $unit_price
 * @property-read float $tax_rate
 * @property-read float $total
 */
class DebitNoteItem extends Model
{
    /** @use HasFactory<DebitNoteItemFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'debit_note_items';

    protected $fillable = [
        'company_id',
        'debit_note_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'tax_rate' => 'float',
            'total' => 'float',
        ];
    }

    /**
     * @return BelongsTo<DebitNote, $this>
     */
    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(DebitNote::class);
    }
}
