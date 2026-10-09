<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One tender against a POS order. An order can settle through several of
 * these, so the till supports split payment (cash + card, and so on).
 *
 * @property-read Carbon|null $paid_at
 */
class PosPayment extends Model
{
    public const METHOD_CASH = 'cash';

    public const METHOD_CARD = 'card';

    public const METHOD_MOBILE = 'mobile';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_OTHER = 'other';

    /** @var list<string> */
    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_MOBILE,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_OTHER,
    ];

    protected $table = 'pos_payments';

    protected $fillable = [
        'pos_order_id',
        'method',
        'amount',
        'reference',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class);
    }
}
