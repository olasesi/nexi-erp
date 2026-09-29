<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(private readonly CurrencyService $currencies) {}

    /**
     * Profit & Loss for a date range, derived from posted journal entries.
     * Returns revenue and expense account summaries plus the net result.
     */
    public function profitAndLoss(int $companyId, string $from, string $to): array
    {
        $lines = $this->journalLinesForRange($companyId, $from, $to);

        $revenue = [];
        $expenses = [];

        foreach ($lines as $type => $group) {
            foreach ($group as $row) {
                if ($type === 'revenue') {
                    $revenue[] = [
                        'code' => $row['account_code'],
                        'name' => $row['account_name'],
                        'balance' => round($row['credit'] - $row['debit'], 2),
                    ];
                } elseif ($type === 'expense') {
                    $expenses[] = [
                        'code' => $row['account_code'],
                        'name' => $row['account_name'],
                        'balance' => round($row['debit'] - $row['credit'], 2),
                    ];
                }
            }
        }

        $totalRevenue = round(collect($revenue)->sum('balance'), 2);
        $totalExpenses = round(collect($expenses)->sum('balance'), 2);

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'total_revenue' => $totalRevenue,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_income' => round($totalRevenue - $totalExpenses, 2),
        ];
    }

    /**
     * Balance Sheet as of a date: ending balances per asset, liability and
     * equity account, plus computed totals.
     */
    public function balanceSheet(int $companyId, string $asOf): array
    {
        $rows = $this->accountBalancesAsOf($companyId, $asOf);

        $assets = $rows->filter(fn ($r) => $r['type'] === 'asset')->values();
        $liabilities = $rows->filter(fn ($r) => $r['type'] === 'liability')->values();
        $equity = $rows->filter(fn ($r) => $r['type'] === 'equity')->values();

        $netIncome = round($rows->filter(fn ($r) => in_array($r['type'], ['revenue', 'expense'], true))->sum(function ($r) {
            return $r['type'] === 'revenue' ? $r['balance'] : -$r['balance'];
        }), 2);

        if ($netIncome !== 0.0) {
            $equity->push([
                'account_id' => null,
                'code' => 'CCE',
                'name' => 'Current Earnings (Net Income)',
                'type' => 'equity',
                'normal_balance' => 'credit',
                'debit' => 0,
                'credit' => 0,
                'balance' => $netIncome,
            ]);
        }

        $totalAssets = round($assets->sum('balance'), 2);
        $totalLiabilities = round($liabilities->sum('balance'), 2);
        $totalEquity = round($equity->sum('balance'), 2);

        return [
            'as_of' => $asOf,
            'net_income' => $netIncome,
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equity,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
        ];
    }

    /**
     * Simple cash-flow view: net movement on cash accounts from the GL
     * plus deposits/withdrawals recorded on bank accounts.
     */
    public function cashFlow(int $companyId, string $from, string $to): array
    {
        $lines = $this->journalLinesForRange($companyId, $from, $to);

        $cashAccounts = ['1010'];
        $inflow = 0.0;
        $outflow = 0.0;

        foreach ($lines['asset'] as $row) {
            if (in_array($row['account_code'], $cashAccounts)) {
                $inflow += $row['debit'];
                $outflow += $row['credit'];
            }
        }

        $bankIn = round((float) BankTransaction::where('company_id', $companyId)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->where('amount', '>', 0)->sum('amount'), 2);
        $bankOut = round((float) BankTransaction::where('company_id', $companyId)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->where('amount', '<', 0)->sum('amount'), 2);

        $inflow = round($inflow + $bankIn, 2);
        $outflow = round($outflow + abs($bankOut), 2);

        return [
            'from' => $from,
            'to' => $to,
            'inflow' => $inflow,
            'outflow' => $outflow,
            'net_cash_flow' => round($inflow - $outflow, 2),
        ];
    }

    /**
     * Open balances grouped into aging buckets (receivable or payable).
     */
    public function aging(int $companyId, string $type = 'receivable'): array
    {
        $invoiceTypes = $type === 'receivable'
            ? ['invoice', 'credit_note']
            : ['bill', 'debit_note'];

        $invoices = Invoice::with('contact:id,first_name,last_name')
            ->where('company_id', $companyId)
            ->whereIn('type', $invoiceTypes)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->get();

        $buckets = [
            'current' => collect(),
            '1_30' => collect(),
            '31_60' => collect(),
            '61_90' => collect(),
            '90_plus' => collect(),
        ];

        foreach ($invoices as $invoice) {
            $dueDate = $invoice->due_date;
            $today = now()->startOfDay();
            $overdueDays = $dueDate ? $dueDate->startOfDay()->diffInDays($today) : 0;

            $bucket = match (true) {
                $dueDate === null || $overdueDays <= 0 => 'current',
                $overdueDays <= 30 => '1_30',
                $overdueDays <= 60 => '31_60',
                $overdueDays <= 90 => '61_90',
                default => '90_plus',
            };

            $buckets[$bucket]->push([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'contact_id' => $invoice->contact_id,
                'contact_name' => $invoice->contact ? trim($invoice->contact->first_name.' '.$invoice->contact->last_name) : '',
                'type' => $invoice->type,
                'due_date' => $invoice->due_date?->toDateString(),
                'days_overdue' => (int) max(0, $overdueDays),
                'total' => (float) $invoice->total,
                'balance_due' => (float) $invoice->balance_due,
            ]);
        }

        return [
            'type' => $type,
            'as_of' => now()->toDateString(),
            'buckets' => [
                'current' => [
                    'amount' => round($buckets['current']->sum('balance_due'), 2),
                    'invoices' => $buckets['current']->values(),
                ],
                '1_30' => [
                    'amount' => round($buckets['1_30']->sum('balance_due'), 2),
                    'invoices' => $buckets['1_30']->values(),
                ],
                '31_60' => [
                    'amount' => round($buckets['31_60']->sum('balance_due'), 2),
                    'invoices' => $buckets['31_60']->values(),
                ],
                '61_90' => [
                    'amount' => round($buckets['61_90']->sum('balance_due'), 2),
                    'invoices' => $buckets['61_90']->values(),
                ],
                '90_plus' => [
                    'amount' => round($buckets['90_plus']->sum('balance_due'), 2),
                    'invoices' => $buckets['90_plus']->values(),
                ],
            ],
            'total_outstanding' => round($invoices->sum('balance_due'), 2),
        ];
    }

    /**
     * Sales (net of credit notes) grouped by product, expressed in the
     * company base currency. Line figures are scaled by each document's FX
     * rate before aggregation.
     *
     * @return array<string, mixed>
     */
    public function salesByProduct(int $companyId, string $from, string $to): array
    {
        $base = $this->currencies->baseCurrency($companyId);
        $lines = collect($this->aggregateSales($companyId, $from, $to, $base, function (InvoiceItem $item) {
            return [
                'key' => 'p'.($item->product_id ?? 'u'.$item->id),
                'product_id' => $item->product_id,
                'product_name' => $item->product ? $item->product->name : $item->description,
            ];
        }));

        return [
            'from' => $from,
            'to' => $to,
            'base_currency' => $base,
            'items' => $lines->sortByDesc('total')->values(),
            'total_quantity' => round($lines->sum('quantity'), 2),
            'total_revenue' => round($lines->sum('total'), 2),
        ];
    }

    /**
     * Sales (net of credit notes) grouped by product category, with items
     * lacking a product or category rolled into an "Uncategorized" bucket.
     *
     * @return array<string, mixed>
     */
    public function salesByCategory(int $companyId, string $from, string $to): array
    {
        $base = $this->currencies->baseCurrency($companyId);
        $lines = collect($this->aggregateSales($companyId, $from, $to, $base, function (InvoiceItem $item) {
            $product = $item->product;
            $categoryId = $product?->category_id;
            $category = $product?->category;

            return [
                'key' => 'c'.($categoryId ?? 'none'),
                'category_id' => $categoryId,
                'category_name' => $category ? $category->name : 'Uncategorized',
            ];
        }));

        return [
            'from' => $from,
            'to' => $to,
            'base_currency' => $base,
            'categories' => $lines->sortByDesc('total')->values(),
            'total_quantity' => round($lines->sum('quantity'), 2),
            'total_revenue' => round($lines->sum('total'), 2),
        ];
    }

    /**
     * Value-added tax collected per rate across sales documents in the range,
     * converted to the company base currency. Credit notes reduce the net and
     * tax owed.
     *
     * @return array<string, mixed>
     */
    public function vatSummary(int $companyId, string $from, string $to): array
    {
        $base = $this->currencies->baseCurrency($companyId);
        $documents = $this->salesDocuments($companyId, $from, $to);

        $rates = collect();

        foreach ($documents as $document) {
            $rate = $this->currencies->rate($document->currency, $base, $companyId);
            $sign = $document->type === 'invoice' ? 1 : -1;

            foreach ($document->items as $item) {
                $key = (string) round((float) $item->tax_rate, 2);
                $entry = $rates->get($key, [
                    'tax_rate' => round((float) $item->tax_rate, 2),
                    'net_amount' => 0.0,
                    'tax_amount' => 0.0,
                    'gross_amount' => 0.0,
                    'documents' => [],
                ]);

                $entry['net_amount'] += $sign * (float) $item->subtotal * $rate;
                $entry['tax_amount'] += $sign * (float) $item->tax_amount * $rate;
                $entry['gross_amount'] += $sign * (float) $item->total * $rate;
                $entry['documents'][$document->id] = true;

                $rates->put($key, $entry);
            }
        }

        $rows = $rates->values()
            ->sortBy('tax_rate')
            ->map(fn (array $row) => [
                'tax_rate' => round($row['tax_rate'], 2),
                'net_amount' => round($row['net_amount'], 2),
                'tax_amount' => round($row['tax_amount'], 2),
                'gross_amount' => round($row['gross_amount'], 2),
                'documents' => count($row['documents']),
            ])
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'base_currency' => $base,
            'rates' => $rows,
            'total_net' => round($rows->sum('net_amount'), 2),
            'total_tax' => round($rows->sum('tax_amount'), 2),
            'total_gross' => round($rows->sum('gross_amount'), 2),
        ];
    }

    /**
     * Posted sales documents (invoices and credit notes) in a date range,
     * eager-loaded with their line products and product categories.
     *
     * @return Collection<int, Invoice>
     */
    protected function salesDocuments(int $companyId, string $from, string $to): Collection
    {
        return Invoice::with('items', 'items.product:id,name,category_id', 'items.product.category:id,name')
            ->where('company_id', $companyId)
            ->whereIn('type', ['invoice', 'credit_note'])
            ->whereIn('status', ['sent', 'partial', 'paid', 'overdue'])
            ->whereDate('issue_date', '>=', $from)
            ->whereDate('issue_date', '<=', $to)
            ->get();
    }

    /**
     * Aggregate signed (invoice positive, credit note negative) sales line
     * figures per group, converted to the base currency using each document's
     * FX rate. The callback returns the descriptor array including the group
     * key under "key".
     *
     * @param  callable(InvoiceItem): array<string, mixed>  $group
     * @return array<int, array<string, mixed>>
     */
    protected function aggregateSales(int $companyId, string $from, string $to, string $base, callable $group): array
    {
        $documents = $this->salesDocuments($companyId, $from, $to);

        $descriptors = [];
        $sums = [];

        foreach ($documents as $document) {
            $rate = $this->currencies->rate($document->currency, $base, $companyId);
            $sign = $document->type === 'invoice' ? 1 : -1;

            foreach ($document->items as $item) {
                $descriptor = $group($item);
                $key = (string) $descriptor['key'];

                if (! array_key_exists($key, $sums)) {
                    $descriptors[$key] = $descriptor;
                    $sums[$key] = [
                        'quantity' => 0.0,
                        'subtotal' => 0.0,
                        'tax' => 0.0,
                        'total' => 0.0,
                    ];
                }

                $sums[$key]['quantity'] += $sign * (float) $item->quantity;
                $sums[$key]['subtotal'] += $sign * (float) $item->subtotal * $rate;
                $sums[$key]['tax'] += $sign * (float) $item->tax_amount * $rate;
                $sums[$key]['total'] += $sign * (float) $item->total * $rate;
            }
        }

        $rows = [];

        foreach ($descriptors as $key => $descriptor) {
            unset($descriptor['key']);

            $totals = $sums[$key];
            $descriptor['quantity'] = round($totals['quantity'], 2);
            $descriptor['subtotal'] = round($totals['subtotal'], 2);
            $descriptor['tax'] = round($totals['tax'], 2);
            $descriptor['total'] = round($totals['total'], 2);

            $rows[] = $descriptor;
        }

        return $rows;
    }

    /**
     * @return array<string, Collection<int, array{account_code: string, account_name: string, debit: float, credit: float}>>
     */
    protected function journalLinesForRange(int $companyId, string $from, string $to): array
    {
        $entries = JournalEntry::where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->with('lines.chartOfAccount')
            ->get();

        $grouped = [
            'asset' => collect(),
            'liability' => collect(),
            'equity' => collect(),
            'revenue' => collect(),
            'expense' => collect(),
        ];

        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $account = $line->chartOfAccount;
                if (! $account) {
                    continue;
                }

                $grouped[$account->type]->push([
                    'account_code' => (string) $account->code,
                    'account_name' => (string) $account->name,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ]);
            }
        }

        return collect($grouped)->map(function (Collection $group) {
            return $group->groupBy('account_code')->map(function ($rows) {
                return [
                    'account_code' => $rows->first()['account_code'],
                    'account_name' => $rows->first()['account_name'],
                    'debit' => round((float) $rows->sum('debit'), 2),
                    'credit' => round((float) $rows->sum('credit'), 2),
                ];
            })->values();
        })->toArray();
    }

    /**
     * Ending account balances as of a date using normal debit/credit signs.
     */
    protected function accountBalancesAsOf(int $companyId, string $asOf): Collection
    {
        $entries = JournalEntry::where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereDate('entry_date', '<=', $asOf)
            ->with('lines.chartOfAccount')
            ->get();

        $balances = collect();

        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $account = $line->chartOfAccount;
                if (! $account) {
                    continue;
                }

                $key = 'account-'.$account->id;

                if (! $balances->has($key)) {
                    $balances->put($key, [
                        'account_id' => $account->id,
                        'code' => (string) $account->code,
                        'name' => (string) $account->name,
                        'type' => (string) $account->type,
                        'normal_balance' => (string) $account->normal_balance,
                        'debit' => 0.0,
                        'credit' => 0.0,
                    ]);
                }

                $current = $balances->get($key);
                $current['debit'] += (float) $line->debit;
                $current['credit'] += (float) $line->credit;
                $balances->put($key, $current);
            }
        }

        return $balances->map(function (array $row) {
            $net = $row['debit'] - $row['credit'];

            return [
                'account_id' => $row['account_id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'normal_balance' => $row['normal_balance'],
                'debit' => round($row['debit'], 2),
                'credit' => round($row['credit'], 2),
                'balance' => round($row['normal_balance'] === 'credit' ? -$net : $net, 2),
            ];
        })->values();
    }

    public function supportedTypes(): array
    {
        return ChartOfAccount::distinct()->pluck('type')->all();
    }
}
