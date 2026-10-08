<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

/**
 * The four Smart Dashboard tab payloads.
 *
 * Every number comes from tables this ERP already maintains: the general
 * ledger for money, the CRM for pipeline, documents for sales and purchases,
 * and products/inventories for stock. Nothing here invents a metric the
 * product does not already model somewhere else.
 *
 * Payloads are cached per company, tab and page so polling the dashboard
 * stays cheap; `ANALYTICS_CACHE_TTL` controls the window and `fresh=1`
 * bypasses it. Each build (hit or miss) is recorded on the metrics surface.
 */
class AnalyticsService
{
    /** Tabs the Smart Dashboard exposes, in nav order. */
    public const TABS = ['executive', 'financial', 'sales', 'operations'];

    /** Invoice statuses that count towards revenue (mirrors the dashboard). */
    private const COUNTED_STATUSES = ['paid', 'partial', 'sent', 'overdue'];

    /** Purchase orders not yet received, cancelled or refunded. */
    private const OPEN_PURCHASE_STATUSES = ['draft', 'confirmed', 'processing', 'shipped'];

    /** Sales orders that still represent open work. */
    private const OPEN_SALES_ORDER_STATUSES = ['draft', 'confirmed', 'processing'];

    /** Months every trend-style series on the tabs plots. */
    private const TREND_MONTHS = 12;

    /** Conversion rate at or above which the executive tab raises the
     * "strong conversion" insight. */
    private const STRONG_CONVERSION_PCT = 50.0;

    public function __construct(
        private readonly ReportService $reports,
        private readonly CrmAnalytics $crm,
        private readonly BusinessHealthService $health,
        private readonly MetricsService $metrics,
    ) {}

    /**
     * Build one tab payload, or serve it from cache when the TTL allows.
     *
     * @param  array{limit: int|null, fresh: bool}  $params
     * @return array<string, mixed>
     */
    public function tab(string $tab, int $companyId, array $params): array
    {
        $limit = $params['limit'] ?? (int) config('analytics.default_limit', 25);
        $ttl = (int) config('analytics.cache_ttl', 300);
        $key = $this->cacheKey($tab, $companyId, $limit, $ttl);

        if (! $params['fresh'] && $key !== null) {
            $cached = Cache::get($key);

            if (is_array($cached)) {
                $this->metrics->recordAnalytics($tab, 0.0, true);
                $cached['cached'] = true;

                return $cached;
            }
        }

        $period = $this->period();
        $started = microtime(true);

        $payload = match ($tab) {
            'executive' => $this->executive($companyId, $limit, $period),
            'financial' => $this->financial($companyId, $limit, $period),
            'sales' => $this->sales($companyId),
            'operations' => $this->operations($companyId, $limit),
            default => throw new InvalidArgumentException("Unknown analytics tab [{$tab}]."),
        };

        $payload = array_merge([
            'tab' => $tab,
            'company_id' => $companyId,
            'period' => $period,
            'generated_at' => now()->toIso8601String(),
            'cached' => false,
        ], $payload);

        $this->metrics->recordAnalytics($tab, microtime(true) - $started, false);

        if ($key !== null) {
            Cache::put($key, $payload, $ttl);
        }

        return $payload;
    }

    /**
     * KPI cards, health score, quick insights, module summaries and the two
     * supporting lists (top customers, latest revenue postings).
     *
     * @param  array{from: string, to: string}  $period
     * @return array<string, mixed>
     */
    private function executive(int $companyId, int $limit, array $period): array
    {
        $pnl = $this->reports->profitAndLoss($companyId, $period['from'], $period['to']);
        $previous = $this->previousPeriod($period);
        $previousPnl = $this->reports->profitAndLoss($companyId, $previous['from'], $previous['to']);

        $revenue = (float) $pnl['total_revenue'];
        $expenses = (float) $pnl['total_expenses'];
        $net = (float) $pnl['net_income'];
        $previousRevenue = (float) $previousPnl['total_revenue'];
        $previousExpenses = (float) $previousPnl['total_expenses'];

        $kpis = $this->crm->kpis($companyId);
        $health = $this->health->report($companyId);

        return [
            'kpis' => [
                'monthly_revenue' => [
                    'value' => round($revenue, 2),
                    'previous' => round($previousRevenue, 2),
                    'change_pct' => $this->changePct($revenue, $previousRevenue),
                ],
                'monthly_expense' => [
                    'value' => round($expenses, 2),
                    'previous' => round($previousExpenses, 2),
                    'change_pct' => $this->changePct($expenses, $previousExpenses),
                ],
                'monthly_net_profit' => [
                    'value' => round($net, 2),
                    'margin_pct' => $this->marginPct($net, $revenue),
                ],
                'sales_pipeline' => [
                    'value' => $kpis['pipeline_value'],
                    'active_leads' => $kpis['active_leads'],
                    'open_deals' => $kpis['open_deals'],
                    'conversion_rate' => $kpis['conversion_rate'],
                ],
                'customers' => Contact::where('company_id', $companyId)
                    ->whereIn('type', ['customer', 'both'])
                    ->count(),
                'products' => Product::where('company_id', $companyId)->count(),
            ],
            'health' => $health,
            'insights' => $this->insights($health['alerts'], $kpis['conversion_rate']),
            'module_summaries' => $this->moduleSummaries($companyId, $kpis, $revenue),
            'top_customers' => $this->topCustomers($companyId, $limit),
            'recent_transactions' => $this->revenueEntries(
                $companyId,
                $period['from'],
                $period['to'],
                $limit,
            ),
        ];
    }

    /**
     * Revenue, expenses, profitability, cash flow, revenue by source, a
     * twelve-month trend and the posted revenue entries behind the period.
     *
     * @param  array{from: string, to: string}  $period
     * @return array<string, mixed>
     */
    private function financial(int $companyId, int $limit, array $period): array
    {
        $pnl = $this->reports->profitAndLoss($companyId, $period['from'], $period['to']);
        $previous = $this->previousPeriod($period);
        $previousPnl = $this->reports->profitAndLoss($companyId, $previous['from'], $previous['to']);

        $revenue = (float) $pnl['total_revenue'];
        $expenses = (float) $pnl['total_expenses'];
        $net = (float) $pnl['net_income'];
        $previousRevenue = (float) $previousPnl['total_revenue'];
        $previousExpenses = (float) $previousPnl['total_expenses'];

        $cash = $this->reports->cashFlow($companyId, $period['from'], $period['to']);
        $trendFrom = now()->startOfDay()->subMonths(self::TREND_MONTHS - 1)->startOfMonth()->toDateString();

        return [
            'revenue' => [
                'total' => round($revenue, 2),
                'previous_total' => round($previousRevenue, 2),
                'growth_pct' => $this->changePct($revenue, $previousRevenue),
                'avg_daily' => $this->averageDaily($revenue, $period),
            ],
            'expenses' => [
                'total' => round($expenses, 2),
                'previous_total' => round($previousExpenses, 2),
                'growth_pct' => $this->changePct($expenses, $previousExpenses),
            ],
            'profitability' => [
                'net' => round($net, 2),
                'margin_pct' => $this->marginPct($net, $revenue),
            ],
            'cash_flow' => [
                'inflow' => round((float) $cash['inflow'], 2),
                'outflow' => round((float) $cash['outflow'], 2),
                'net' => round((float) $cash['net_cash_flow'], 2),
            ],
            'revenue_by_source' => $pnl['revenue'],
            'trend' => $this->reports->monthlySeries($companyId, $trendFrom, $period['to']),
            'transactions' => [
                'total' => $this->revenueEntryCount($companyId, $period['from'], $period['to']),
                'limit' => $limit,
                'data' => $this->revenueEntries($companyId, $period['from'], $period['to'], $limit),
            ],
        ];
    }

    /**
     * Pipeline KPIs, the funnel, source performance, monthly lead volume and
     * sales against purchases over the trailing year.
     *
     * @return array<string, mixed>
     */
    private function sales(int $companyId): array
    {
        $kpis = $this->crm->kpis($companyId);

        return [
            'kpis' => [
                'active_leads' => $kpis['active_leads'],
                'pipeline_value' => $kpis['pipeline_value'],
                'conversion_rate' => $kpis['conversion_rate'],
                'active_deals' => $kpis['open_deals'],
                'quotations' => Quotation::where('company_id', $companyId)->count(),
                'sales_orders' => SalesOrder::where('company_id', $companyId)
                    ->whereIn('status', self::OPEN_SALES_ORDER_STATUSES)
                    ->count(),
                'invoices' => Invoice::where('company_id', $companyId)
                    ->where('type', 'invoice')
                    ->whereIn('status', self::COUNTED_STATUSES)
                    ->count(),
            ],
            'lead_funnel' => $this->crm->statusCounts($companyId),
            'leads_by_source' => $this->crm->sourceDetail($companyId),
            'monthly_leads' => $this->crm->monthlyVolume($companyId, self::TREND_MONTHS),
            'sales_vs_purchase' => $this->salesVsPurchase($companyId),
        ];
    }

    /**
     * Stock KPIs, stock grouped by category and warehouse, the purchase
     * pipeline and a bounded product list.
     *
     * @return array<string, mixed>
     */
    private function operations(int $companyId, int $limit): array
    {
        return [
            'kpis' => [
                'total_products' => Product::where('company_id', $companyId)->count(),
                'stock_value' => $this->stockValue($companyId),
                'out_of_stock' => $this->outOfStockQuery($companyId)->count(),
                'low_stock' => $this->lowStockQuery($companyId)->count(),
                'active_warehouses' => Warehouse::where('company_id', $companyId)
                    ->where('is_active', true)
                    ->count(),
            ],
            'stock_by_category' => $this->stockByCategory($companyId),
            'stock_by_warehouse' => $this->stockByWarehouse($companyId),
            'purchases' => $this->purchaseSummary($companyId, $limit),
            'products' => $this->productList($companyId, $limit),
        ];
    }

    /**
     * Advisor alerts reshaped for the Quick Insights widget, plus a
     * generated positive when conversion is strong enough to celebrate.
     *
     * @param  list<array{key: string, severity: string, domain: string, title: string, detail: string}>  $alerts
     * @return list<array{key: string, severity: string, domain: string, title: string, text: string}>
     */
    private function insights(array $alerts, float $conversionRate): array
    {
        $insights = [];

        if ($conversionRate >= self::STRONG_CONVERSION_PCT) {
            $insights[] = [
                'key' => 'strong_conversion',
                'severity' => 'positive',
                'domain' => 'sales',
                'title' => 'Strong Conversion Rate',
                'text' => sprintf('%.2f%% lead conversion rate achieved. Your sales team is performing well.', $conversionRate),
            ];
        }

        foreach ($alerts as $alert) {
            $insights[] = [
                'key' => $alert['key'],
                'severity' => $alert['severity'],
                'domain' => $alert['domain'],
                'title' => $alert['title'],
                'text' => $alert['detail'],
            ];
        }

        return $insights;
    }

    /**
     * Per-module headline numbers, keyed the way the SPA renders the grid.
     *
     * @param  array{active_leads: int, open_deals: int, pipeline_value: float, conversion_rate: float}  $kpis
     * @return array{crm: array<string, float|int>, account: array<string, float|int>, sales: array<string, float|int>, inventory: array<string, float|int>}
     */
    private function moduleSummaries(int $companyId, array $kpis, float $monthlyRevenue): array
    {
        return [
            'crm' => [
                'active_leads' => $kpis['active_leads'],
                'conversion_rate' => $kpis['conversion_rate'],
                'pipeline_value' => $kpis['pipeline_value'],
            ],
            'account' => [
                'cash_balance' => $this->cashBalance($companyId),
                'pending_invoices' => $this->pendingDocuments($companyId, ['invoice']),
                'pending_bills' => $this->pendingDocuments($companyId, ['bill', 'debit_note']),
            ],
            'sales' => [
                'monthly_revenue' => round($monthlyRevenue, 2),
                'quotations' => Quotation::where('company_id', $companyId)->count(),
                'orders' => SalesOrder::where('company_id', $companyId)
                    ->whereIn('status', self::OPEN_SALES_ORDER_STATUSES)
                    ->count(),
            ],
            'inventory' => [
                'low_stock' => $this->lowStockQuery($companyId)->count(),
                'out_of_stock' => $this->outOfStockQuery($companyId)->count(),
                'total_stock_value' => $this->stockValue($companyId),
            ],
        ];
    }

    /**
     * Lifetime invoice leaders, biggest first.
     *
     * @return list<array{contact_id: int, name: string, total: float, invoices: int}>
     */
    private function topCustomers(int $companyId, int $limit): array
    {
        $rows = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('type', 'invoice')
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereNotNull('contact_id')
            ->whereNull('deleted_at')
            ->groupBy('contact_id')
            ->selectRaw('contact_id, sum(total) as total, count(*) as invoices')
            ->orderByDesc('total')
            ->orderByDesc('contact_id')
            ->limit($limit)
            ->get();

        $contacts = Contact::where('company_id', $companyId)
            ->whereIn('id', $rows->pluck('contact_id'))
            ->get()
            ->keyBy('id')
            ->all();

        $customers = [];

        foreach ($rows as $row) {
            $contact = $contacts[(int) $row->contact_id] ?? null;

            $customers[] = [
                'contact_id' => (int) $row->contact_id,
                'name' => $contact === null ? 'Unknown' : $contact->full_name,
                'total' => round((float) $row->total, 2),
                'invoices' => (int) $row->invoices,
            ];
        }

        return $customers;
    }

    /**
     * Posted journal entries touching revenue accounts inside the range,
     * newest first, with the accounts each entry moved.
     *
     * @return list<array{entry_id: int, entry_number: string, description: string|null, entry_date: string, amount: float, accounts: list<string>}>
     */
    private function revenueEntries(int $companyId, string $from, string $to, int $limit): array
    {
        $entries = $this->revenueEntryQuery($companyId, $from, $to)
            ->groupBy('journal_entries.id')
            ->selectRaw(
                'journal_entries.id as entry_id, journal_entries.entry_number, journal_entries.description, '
                .'journal_entries.entry_date, sum(journal_entry_lines.credit - journal_entry_lines.debit) as amount',
            )
            ->orderByDesc('journal_entries.entry_date')
            ->orderByDesc('journal_entries.id')
            ->limit($limit)
            ->get();

        if ($entries->isEmpty()) {
            return [];
        }

        $accounts = $this->revenueEntryQuery($companyId, $from, $to)
            ->whereIn('journal_entries.id', $entries->pluck('entry_id'))
            ->groupBy('journal_entries.id')
            ->selectRaw('journal_entries.id as entry_id, group_concat(distinct chart_of_accounts.name) as names')
            ->get()
            ->keyBy('entry_id');

        $data = [];

        foreach ($entries as $entry) {
            $names = $accounts->get($entry->entry_id)?->names;

            $data[] = [
                'entry_id' => (int) $entry->entry_id,
                'entry_number' => (string) $entry->entry_number,
                'description' => $entry->description === null ? null : (string) $entry->description,
                'entry_date' => Carbon::parse($entry->entry_date)->toDateString(),
                'amount' => round((float) $entry->amount, 2),
                'accounts' => $names === null || $names === ''
                    ? []
                    : array_map('trim', explode(',', (string) $names)),
            ];
        }

        return $data;
    }

    /**
     * How many distinct revenue entries the range holds, for the list header.
     */
    private function revenueEntryCount(int $companyId, string $from, string $to): int
    {
        return (int) $this->revenueEntryQuery($companyId, $from, $to)
            ->distinct()
            ->count('journal_entries.id');
    }

    /**
     * Posted entries with at least one revenue account line, joined so both
     * the aggregate and the account-name queries share one definition.
     */
    private function revenueEntryQuery(int $companyId, string $from, string $to): QueryBuilder
    {
        return DB::table('journal_entries')
            ->join('journal_entry_lines', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('chart_of_accounts.type', 'revenue')
            ->whereDate('journal_entries.entry_date', '>=', $from)
            ->whereDate('journal_entries.entry_date', '<=', $to);
    }

    /**
     * Twelve months of invoiced sales against billed purchases, zero-filled
     * so quiet months still get a column.
     *
     * @return list<array{month: string, sales: float, purchases: float}>
     */
    private function salesVsPurchase(int $companyId): array
    {
        $from = now()->startOfDay()->subMonths(self::TREND_MONTHS - 1)->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $rows = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereIn('type', ['invoice', 'bill'])
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereNull('deleted_at')
            ->whereDate('issue_date', '>=', $from)
            ->whereDate('issue_date', '<=', $to)
            ->selectRaw(
                'substr(issue_date, 1, 7) as month, '
                ."sum(case when type = 'invoice' then total else 0 end) as sales, "
                ."sum(case when type = 'bill' then total else 0 end) as purchases",
            )
            ->groupBy('month')
            ->get()
            ->keyBy('month')
            ->all();

        $series = [];

        foreach ($this->reports->monthLabels($from, $to) as $month) {
            $row = $rows[$month] ?? new stdClass;

            $series[] = [
                'month' => $month,
                'sales' => round((float) ($row->sales ?? 0), 2),
                'purchases' => round((float) ($row->purchases ?? 0), 2),
            ];
        }

        return $series;
    }

    /**
     * Current month window for the KPI cards.
     *
     * @return array{from: string, to: string}
     */
    private function period(): array
    {
        return [
            'from' => now()->startOfDay()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ];
    }

    /**
     * The full calendar month before the given period, for growth figures.
     *
     * @param  array{from: string, to: string}  $period
     * @return array{from: string, to: string}
     */
    private function previousPeriod(array $period): array
    {
        $start = Carbon::parse($period['from']);

        return [
            'from' => $start->copy()->subMonth()->toDateString(),
            'to' => $start->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * Percentage change against a previous value. A zero base reads as the
     * full swing rather than infinity: growth from nothing is 100, nothing
     * from something is -100.
     */
    private function changePct(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current > 0.0 ? 100.0 : ($current < 0.0 ? -100.0 : 0.0);
        }

        return round(($current - $previous) * 100 / $previous, 2);
    }

    /**
     * Net as a share of revenue, or 0 while there is no revenue to divide.
     */
    private function marginPct(float $net, float $revenue): float
    {
        return $revenue == 0.0 ? 0.0 : round($net * 100 / $revenue, 2);
    }

    /**
     * Period revenue spread over the days it covers, for the daily run rate.
     *
     * @param  array{from: string, to: string}  $period
     */
    private function averageDaily(float $revenue, array $period): float
    {
        $days = Carbon::parse($period['from'])->diffInDays(Carbon::parse($period['to'])) + 1;

        return round($revenue / max(1.0, $days), 2);
    }

    /**
     * Cash on hand: opening bank balances plus receipts less payments.
     */
    private function cashBalance(int $companyId): float
    {
        $opening = (float) BankAccount::where('company_id', $companyId)->sum('opening_balance');
        $received = (float) Payment::where('company_id', $companyId)
            ->where('type', 'receipt')
            ->where('status', 'completed')
            ->sum('amount');
        $paid = (float) Payment::where('company_id', $companyId)
            ->where('type', 'payment')
            ->where('status', 'completed')
            ->sum('amount');

        return round($opening + $received - $paid, 2);
    }

    /**
     * Unsettled customer or vendor documents with a balance still due.
     *
     * @param  list<string>  $types
     */
    private function pendingDocuments(int $companyId, array $types): int
    {
        return Invoice::where('company_id', $companyId)
            ->whereIn('type', $types)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->count();
    }

    /**
     * On-hand value of physical stock: quantity times cost, per product.
     */
    private function stockValue(int $companyId): float
    {
        $value = Product::where('company_id', $companyId)
            ->where('type', 'product')
            ->selectRaw('sum(stock_quantity * cost_price) as total')
            ->value('total');

        return round((float) $value, 2);
    }

    /**
     * @return Builder<Product>
     */
    private function lowStockQuery(int $companyId)
    {
        return Product::where('company_id', $companyId)
            ->where('type', 'product')
            ->whereNotNull('min_stock_level')
            ->whereColumn('stock_quantity', '<=', 'min_stock_level');
    }

    /**
     * @return Builder<Product>
     */
    private function outOfStockQuery(int $companyId)
    {
        return Product::where('company_id', $companyId)
            ->where('type', 'product')
            ->where('stock_quantity', '<=', 0);
    }

    /**
     * Physical stock grouped by category, names resolved in one lookup and
     * the result sorted so the bar chart has a stable order.
     *
     * @return list<array{category_id: int|null, category: string, products: int, quantity: float, value: float}>
     */
    private function stockByCategory(int $companyId): array
    {
        $rows = DB::table('products')
            ->leftJoin('product_categories', function ($join) {
                $join->on('product_categories.id', '=', 'products.category_id')
                    ->whereNull('product_categories.deleted_at');
            })
            ->where('products.company_id', $companyId)
            ->where('products.type', 'product')
            ->whereNull('products.deleted_at')
            ->groupBy('products.category_id', 'product_categories.name')
            ->selectRaw(
                'products.category_id, product_categories.name as category, count(*) as products, '
                .'sum(products.stock_quantity) as quantity, sum(products.stock_quantity * products.cost_price) as value',
            )
            ->orderBy('product_categories.name')
            ->get();

        $categories = [];

        foreach ($rows as $row) {
            $categories[] = [
                'category_id' => $row->category_id === null ? null : (int) $row->category_id,
                'category' => (string) ($row->category ?? 'Uncategorised'),
                'products' => (int) $row->products,
                'quantity' => round((float) ($row->quantity ?? 0), 2),
                'value' => round((float) ($row->value ?? 0), 2),
            ];
        }

        return $categories;
    }

    /**
     * Stock per warehouse, including the empty ones so the chart shows the
     * whole footprint. Inventory rows are only counted when their product
     * belongs to the requesting company.
     *
     * @return list<array{warehouse_id: int, warehouse: string, quantity: float, value: float}>
     */
    private function stockByWarehouse(int $companyId): array
    {
        $rows = DB::table('warehouses')
            ->leftJoin('inventories', 'inventories.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'inventories.product_id')
                    ->whereNull('products.deleted_at');
            })
            ->where('warehouses.company_id', $companyId)
            ->whereNull('warehouses.deleted_at')
            ->where(function ($query) use ($companyId) {
                $query->whereNull('products.company_id')
                    ->orWhere('products.company_id', $companyId);
            })
            ->groupBy('warehouses.id', 'warehouses.name')
            ->selectRaw(
                'warehouses.id as warehouse_id, warehouses.name as warehouse, '
                .'coalesce(sum(inventories.quantity), 0) as quantity, '
                .'coalesce(sum(case when products.id is null then 0 '
                .'else inventories.quantity * products.cost_price end), 0) as value',
            )
            ->orderBy('warehouses.name')
            ->get();

        $warehouses = [];

        foreach ($rows as $row) {
            $warehouses[] = [
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse' => (string) $row->warehouse,
                'quantity' => round((float) $row->quantity, 2),
                'value' => round((float) $row->value, 2),
            ];
        }

        return $warehouses;
    }

    /**
     * Open purchase commitments and the vendors behind them.
     *
     * @return array{open_orders: int, order_value: float, pending_receipts: int, top_vendors: list<array{vendor_id: int, vendor: string, orders: int, value: float}>}
     */
    private function purchaseSummary(int $companyId, int $limit): array
    {
        $open = PurchaseOrder::where('company_id', $companyId)
            ->whereIn('status', self::OPEN_PURCHASE_STATUSES);

        $rows = DB::table('purchase_orders')
            ->where('company_id', $companyId)
            ->whereIn('status', self::OPEN_PURCHASE_STATUSES)
            ->whereNull('deleted_at')
            ->whereNotNull('contact_id')
            ->groupBy('contact_id')
            ->selectRaw('contact_id, count(*) as orders, sum(total) as value')
            ->orderByDesc('value')
            ->orderByDesc('contact_id')
            ->limit($limit)
            ->get();

        $contacts = Contact::where('company_id', $companyId)
            ->whereIn('id', $rows->pluck('contact_id'))
            ->get()
            ->keyBy('id')
            ->all();

        $vendors = [];

        foreach ($rows as $row) {
            $contact = $contacts[(int) $row->contact_id] ?? null;

            $vendors[] = [
                'vendor_id' => (int) $row->contact_id,
                'vendor' => $contact === null ? 'Unknown' : $contact->full_name,
                'orders' => (int) $row->orders,
                'value' => round((float) $row->value, 2),
            ];
        }

        return [
            'open_orders' => (clone $open)->count(),
            'order_value' => round((float) (clone $open)->sum('total'), 2),
            'pending_receipts' => PurchaseReceipt::where('company_id', $companyId)
                ->where('status', '!=', 'received')
                ->count(),
            'top_vendors' => $vendors,
        ];
    }

    /**
     * Bounded product list for the operations table. Full record browsing
     * stays on the products module's own paginated endpoint.
     *
     * @return array{total: int, limit: int, data: list<array{product_id: int, name: string, sku: string|null, category: string, type: string, unit: string|null, stock_quantity: int, cost_price: float, sale_price: float, stock_value: float, min_stock_level: int, status: string}>}
     */
    private function productList(int $companyId, int $limit): array
    {
        $query = DB::table('products')
            ->where('products.company_id', $companyId)
            ->whereNull('products.deleted_at');
        $total = (clone $query)->count();

        $rows = $query
            ->leftJoin('product_categories', function ($join) {
                $join->on('product_categories.id', '=', 'products.category_id')
                    ->whereNull('product_categories.deleted_at');
            })
            ->selectRaw('products.*, product_categories.name as category')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->limit($limit)
            ->get();

        $products = [];

        foreach ($rows as $row) {
            $stockQuantity = (int) $row->stock_quantity;
            $minStockLevel = (int) $row->min_stock_level;

            $products[] = [
                'product_id' => (int) $row->id,
                'name' => (string) $row->name,
                'sku' => $row->sku === null ? null : (string) $row->sku,
                'category' => (string) ($row->category ?? 'Uncategorised'),
                'type' => (string) $row->type,
                'unit' => $row->unit === null ? null : (string) $row->unit,
                'stock_quantity' => $stockQuantity,
                'cost_price' => round((float) $row->cost_price, 2),
                'sale_price' => round((float) $row->sale_price, 2),
                'stock_value' => round($stockQuantity * (float) $row->cost_price, 2),
                'min_stock_level' => $minStockLevel,
                'status' => $this->stockStatus($stockQuantity, $minStockLevel),
            ];
        }

        return [
            'total' => $total,
            'limit' => $limit,
            'data' => $products,
        ];
    }

    /**
     * Machine-readable stock state; the SPA renders the label.
     */
    private function stockStatus(int $stockQuantity, int $minStockLevel): string
    {
        if ($stockQuantity <= 0) {
            return 'out_of_stock';
        }

        if ($stockQuantity <= $minStockLevel) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * Cache key for a tab build. The time bucket rolls with the TTL, so a
     * stale key can outlive its data by at most one TTL window while the
     * entries themselves expire on the same schedule.
     */
    private function cacheKey(string $tab, int $companyId, int $limit, int $ttl): ?string
    {
        if ($ttl <= 0) {
            return null;
        }

        $bucket = (int) floor(time() / $ttl);

        return sprintf('analytics:%s:%d:%d:%d', $tab, $companyId, $bucket, $limit);
    }
}
