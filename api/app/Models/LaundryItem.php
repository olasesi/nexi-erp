<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\LaundryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaundryItem extends Model
{
    /** @use HasFactory<LaundryItemFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const SERVICE_WASH = 'wash';

    public const SERVICE_DRY_CLEAN = 'dry_clean';

    public const SERVICE_IRON = 'iron';

    public const SERVICE_FOLD = 'fold';

    public const SERVICES = [
        self::SERVICE_WASH,
        self::SERVICE_DRY_CLEAN,
        self::SERVICE_IRON,
        self::SERVICE_FOLD,
    ];

    protected $table = 'laundry_items';

    protected $fillable = [
        'company_id',
        'laundry_order_id',
        'product_id',
        'description',
        'service',
        'quantity',
        'unit_price',
        'weight_kg',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'float',
            'weight_kg' => 'float',
            'subtotal' => 'float',
        ];
    }

    /**
     * @return BelongsTo<LaundryOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
