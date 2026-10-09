<?php

namespace App\Services;

use App\Models\PosOrder;
use Illuminate\Support\Facades\DB;

/**
 * POS till aggregates behind GET /dashboard/pos-summary and the POS module's
 * summary feed. Every figure comes from a small fixed number of grouped
 * statements, so the cost does not grow with the number of orders.
 */
class PosAnalytics
{
    /**
     * Takings for one trading day plus the payment mix and best sellers that
     * came out of it.
     *
     * @return array{
     *     date: string,
     *     todays_sales: float,
     *     todays_transactions: int,
     *     todays_items_sold: float,
     *     todays_tax: float,
     *     todays_discount: float,
     *     average_sale: float,
     *     by_payment_method: list<array{method: string, count: int, total: float}>,
     *     top_products: list<array{product_id: int|null, product_name: string, quantity: float, total: float}>
     * }
     */
    public function summary(int $companyId, ?string $date = null, int $topLimit = 5): array
    {
        $date = $date ?? now()->toDateString();

        $orders = PosOrder::where('company_id', $companyId)
            ->whereIn('status', PosOrder::COUNTED_STATUSES)
            ->whereDate('order_date', $date);

        $transactions = (clone $orders)->count();
        $sales = round((float) (clone $orders)->sum('total'), 2);
        $tax = round((float) (clone $orders)->sum('tax_amount'), 2);
        $discount = round((float) (clone $orders)->sum('discount_amount'), 2);

        $itemsSold = round((float) DB::table('pos_order_items')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.pos_order_id')
            ->where('pos_orders.company_id', $companyId)
            ->whereIn('pos_orders.status', PosOrder::COUNTED_STATUSES)
            ->whereDate('pos_orders.order_date', $date)
            ->sum('pos_order_items.quantity'), 2);

        $byPaymentMethod = DB::table('pos_payments')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.pos_order_id')
            ->where('pos_orders.company_id', $companyId)
            ->whereIn('pos_orders.status', PosOrder::COUNTED_STATUSES)
            ->whereDate('pos_orders.order_date', $date)
            ->groupBy('pos_payments.method')
            ->selectRaw('pos_payments.method as method, count(*) as count, coalesce(sum(pos_payments.amount), 0) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'method' => (string) $row->method,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();

        $topProducts = DB::table('pos_order_items')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.pos_order_id')
            ->where('pos_orders.company_id', $companyId)
            ->whereIn('pos_orders.status', PosOrder::COUNTED_STATUSES)
            ->whereDate('pos_orders.order_date', $date)
            ->groupBy('pos_order_items.product_id', 'pos_order_items.product_name')
            ->selectRaw('pos_order_items.product_id as product_id, pos_order_items.product_name as product_name, coalesce(sum(pos_order_items.quantity), 0) as quantity, coalesce(sum(pos_order_items.total), 0) as total')
            ->orderByDesc('quantity')
            ->orderByDesc('total')
            ->limit(max($topLimit, 1))
            ->get()
            ->map(fn ($row): array => [
                'product_id' => $row->product_id === null ? null : (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'quantity' => round((float) $row->quantity, 2),
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();

        return [
            'date' => $date,
            'todays_sales' => $sales,
            'todays_transactions' => $transactions,
            'todays_items_sold' => $itemsSold,
            'todays_tax' => $tax,
            'todays_discount' => $discount,
            'average_sale' => $transactions === 0 ? 0.0 : round($sales / $transactions, 2),
            'by_payment_method' => $byPaymentMethod,
            'top_products' => $topProducts,
        ];
    }
}
