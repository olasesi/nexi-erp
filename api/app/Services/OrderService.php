<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;

class OrderService
{
    /**
     * Normalize raw item payloads into persisted line rows.
     * Enriches from the product catalog when a product_id is given,
     * then computes per-line tax, subtotal and total.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $line) {
            $product = isset($line['product_id']) ? Product::find($line['product_id']) : null;

            $unitPrice = (float) ($line['unit_price'] ?? $product?->sale_price ?? 0);
            $quantity = (float) ($line['quantity'] ?? 1);
            $taxRate = (float) ($line['tax_rate'] ?? $product?->tax_rate ?? 0);
            $discount = (float) ($line['discount_amount'] ?? 0);

            $subtotal = round($unitPrice * $quantity, 2);
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total = round($subtotal + $taxAmount - $discount, 2);

            $lines[] = [
                'product_id' => $line['product_id'] ?? $product?->id ?? null,
                'product_name' => $line['product_name'] ?? $product?->name ?? 'Item',
                'product_sku' => $line['product_sku'] ?? $product?->sku ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discount,
                'subtotal' => $subtotal,
                'total' => $total,
            ];
        }

        return $lines;
    }

    /**
     * Aggregate line totals into the order-level amount columns.
     *
     * @return array{subtotal: float, tax_amount: float, discount_amount: float, total: float}
     */
    public function computeTotals(array $lines): array
    {
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $tax = round(array_sum(array_column($lines, 'tax_amount')), 2);
        $discount = round(array_sum(array_column($lines, 'discount_amount')), 2);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'total' => round($subtotal + $tax - $discount, 2),
        ];
    }

    /**
     * Normalize invoice item payloads into persisted rows, enriching from
     * the product catalog and computing per-line tax/subtotal/total.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildLinesForInvoices(array $items): array
    {
        $lines = [];

        foreach ($items as $line) {
            $product = isset($line['product_id']) ? Product::find($line['product_id']) : null;

            $unitPrice = (float) ($line['unit_price'] ?? $product?->sale_price ?? 0);
            $quantity = (float) ($line['quantity'] ?? 1);
            $taxRate = (float) ($line['tax_rate'] ?? $product?->tax_rate ?? 0);
            $discount = (float) ($line['discount_amount'] ?? 0);

            $subtotal = round($unitPrice * $quantity, 2);
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total = round($subtotal + $taxAmount - $discount, 2);

            $lines[] = [
                'product_id' => $line['product_id'] ?? $product?->id ?? null,
                'description' => $line['description'] ?? $product?->name ?? 'Item',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discount,
                'subtotal' => $subtotal,
                'total' => $total,
            ];
        }

        return $lines;
    }

    /**
     * Reconcile persisted invoice items against a new payload.
     */
    public function syncInvoiceItems(Invoice $invoice, array $items): void
    {
        $lines = $this->buildLinesForInvoices($items);

        $incomingIds = collect($items)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $invoice->items()
            ->whereNotIn('id', $incomingIds ?: [0])
            ->delete();

        foreach ($lines as $index => $line) {
            $id = (int) ($items[$index]['id'] ?? 0);

            if ($id) {
                $invoice->items()->where('id', $id)->update($line);
            } else {
                $invoice->items()->create($line);
            }
        }
    }

    /**
     * Reconcile persisted items against a new payload inside a transaction.
     * Matches existing rows by "id" when present, otherwise creates new rows,
     * and deletes rows no longer present in the payload.
     */
    public function syncItems($order, array $items): void
    {
        $provider = $this->buildLines($items);

        $incomingIds = collect($items)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $order->items()
            ->whereNotIn('id', $incomingIds ?: [0])
            ->delete();

        foreach ($provider as $index => $line) {
            $id = (int) ($items[$index]['id'] ?? 0);

            if ($id) {
                $order->items()->where('id', $id)->update($line);
            } else {
                $order->items()->create($line);
            }
        }
    }
}
