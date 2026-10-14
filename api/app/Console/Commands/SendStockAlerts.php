<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Notifications\LowStockNotification;
use App\Services\NotificationService;
use App\Services\WebhookService;
use Illuminate\Console\Command;

/**
 * Scans active products whose stock on hand has reached the reorder level and
 * raises a low-stock notice (with a `stock.low` webhook) for each company.
 */
class SendStockAlerts extends Command
{
    protected $signature = 'stock:alerts
                            {--company= : Only process a single company}';

    protected $description = 'Raise low-stock notifications for products at or below their reorder level';

    public function handle(NotificationService $notifications): int
    {
        $company = $this->option('company');

        $query = Product::query()
            ->withoutGlobalScope('company_scope')
            ->where('is_active', true)
            ->where('min_stock_level', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'min_stock_level')
            ->when($company !== null, fn ($builder) => $builder->where('company_id', (int) $company));

        $alerted = 0;

        $query->orderBy('id')->chunkById(200, function ($products) use (&$alerted, $notifications): void {
            foreach ($products as $product) {
                WebhookService::dispatch('stock.low', $product, [
                    'stock_quantity' => (int) $product->stock_quantity,
                    'min_stock_level' => (int) $product->min_stock_level,
                ]);

                $notifications->toCompany((int) $product->company_id, new LowStockNotification($product));

                $alerted++;
            }
        });

        $this->info(sprintf('Raised %d low-stock alert(s).', $alerted));

        return self::SUCCESS;
    }
}
