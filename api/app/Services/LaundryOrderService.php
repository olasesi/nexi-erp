<?php

namespace App\Services;

use App\Models\LaundryOrder;
use Illuminate\Validation\ValidationException;

class LaundryOrderService
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    /**
     * Company-scoped order number, e.g. LW-2610-0007.
     */
    public function generateNumber(int $companyId): string
    {
        $prefix = 'LW-'.date('ym');

        $count = LaundryOrder::query()
            ->where('company_id', $companyId)
            ->where('order_number', 'like', "{$prefix}-%")
            ->count();

        return sprintf('%s-%04d', $prefix, $count + 1);
    }

    /**
     * Recompute the order's money from its live items. Subtotal is the sum of
     * line subtotals, tax is applied to it, then any discount comes off.
     */
    public function recalculate(LaundryOrder $order): LaundryOrder
    {
        $subtotal = (float) $order->items()->sum('subtotal');
        $taxAmount = round($subtotal * ((float) $order->tax_rate) / 100, 2);
        $total = round($subtotal + $taxAmount - (float) $order->discount_amount, 2);

        $order->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ])->save();

        return $order;
    }

    /**
     * Step the order to the next garment stage. Delivered and cancelled orders
     * have left the flow and cannot advance.
     */
    public function advance(LaundryOrder $order): LaundryOrder
    {
        $next = $order->nextStatus();

        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => "A {$order->status} order cannot be advanced.",
            ]);
        }

        $order->status = $next;
        $order->completed_at = $next === LaundryOrder::STATUS_DELIVERED ? now() : null;
        $order->save();

        $this->metrics->recordVertical('laundry', $next);

        return $order;
    }

    public function cancel(LaundryOrder $order): LaundryOrder
    {
        if (in_array($order->status, [LaundryOrder::STATUS_DELIVERED, LaundryOrder::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => "A {$order->status} order cannot be cancelled.",
            ]);
        }

        $order->status = LaundryOrder::STATUS_CANCELLED;
        $order->save();

        $this->metrics->recordVertical('laundry', 'cancelled');

        return $order;
    }
}
