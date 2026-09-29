<?php

namespace App\Services\Import;

use App\Models\Product;

class ProductImporter implements RowImporter
{
    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['name', 'sku', 'barcode', 'type', 'unit', 'sale_price', 'purchase_price', 'stock_quantity', 'min_stock_level'];
    }

    /**
     * @param  array<string, string>  $row
     */
    public function error(array $row): ?string
    {
        if (trim($row['name'] ?? '') === '') {
            return 'name is required.';
        }

        $type = trim($row['type'] ?? '') ?: 'product';

        if (! in_array($type, ['product', 'service'], true)) {
            return 'type must be product or service.';
        }

        foreach (['sale_price', 'purchase_price'] as $field) {
            $value = trim($row[$field] ?? '');

            if ($value !== '' && ! is_numeric($value)) {
                return $field.' must be numeric.';
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    public function import(array $row, int $companyId): string
    {
        $sku = trim($row['sku'] ?? '');

        $attributes = [
            'name' => trim($row['name'] ?? ''),
            'sku' => $sku === '' ? null : $sku,
            'barcode' => trim($row['barcode'] ?? '') ?: null,
            'type' => trim($row['type'] ?? '') ?: 'product',
            'unit' => trim($row['unit'] ?? '') ?: null,
            'sale_price' => (float) ($row['sale_price'] ?? 0),
            'purchase_price' => (float) ($row['purchase_price'] ?? 0),
            'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
            'min_stock_level' => (int) ($row['min_stock_level'] ?? 0),
        ];

        $existing = $sku === ''
            ? null
            : Product::where('company_id', $companyId)->where('sku', $sku)->first();

        if ($existing) {
            $existing->update($attributes);

            return self::UPDATED;
        }

        Product::create($attributes + ['company_id' => $companyId]);

        return self::CREATED;
    }
}
