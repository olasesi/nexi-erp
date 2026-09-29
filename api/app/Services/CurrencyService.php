<?php

namespace App\Services;

use App\Models\CurrencyRate;

class CurrencyService
{
    /**
     * Resolve the company base currency, falling back to the configured
     * default currency or USD when the company has none defined.
     */
    public function baseCurrency(?int $companyId = null): string
    {
        /** @var array<int, array<int, mixed>> $currencies */
        $currencies = config('currencies');

        $default = collect($currencies)->first(fn (array $currency) => $currency[4]);

        return (string) ($default[2] ?? 'USD');
    }

    /**
     * Look up the multiplier to express 1 unit of $from in $to, honouring
     * company-level overrides and falling back to a 1:1 rate.
     */
    public function rate(string $from, string $to, ?int $companyId = null): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to || $companyId === null) {
            return 1.0;
        }

        return $this->toBase($from, $companyId) / max($this->toBase($to, $companyId), 0.000001);
    }

    /**
     * How many base-currency units one unit of $currency is worth for a
     * company, falling back to 1:1 when no rate row exists.
     */
    protected function toBase(string $currency, int $companyId): float
    {
        $base = $this->baseCurrency($companyId);

        if (strtoupper($currency) === $base) {
            return 1.0;
        }

        $row = CurrencyRate::where('company_id', $companyId)
            ->where('base_currency', $base)
            ->where('currency', strtoupper($currency))
            ->first();

        return $row ? (float) $row->rate : 1.0;
    }

    /**
     * Convert an amount expressed in $from into the requested currency.
     */
    public function convert(float $amount, string $from, string $to, ?int $companyId = null): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return round($amount, 2);
        }

        $converted = $amount * $this->rate($from, $to, $companyId);

        return round($converted, 2);
    }

    /**
     * Summary block for resources: the base-currency equivalent of an amount
     * in the document's currency plus the effective rate used.
     *
     * @return array{base_currency: string, rate: float, base_total: float}
     */
    public function fx(string $currency, float $total, ?int $companyId = null): array
    {
        $base = $this->baseCurrency($companyId);

        return [
            'base_currency' => $base,
            'rate' => $this->rate($currency, $base, $companyId),
            'base_total' => $this->convert($total, $currency, $base, $companyId),
        ];
    }
}
