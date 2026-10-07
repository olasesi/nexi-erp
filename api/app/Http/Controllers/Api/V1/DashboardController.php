<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\CrmStage;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\BusinessSettingsService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Document statuses that count towards the dashboard totals. */
    private const COUNTED_STATUSES = ['paid', 'partial', 'sent', 'overdue'];

    /** Cap on daily_sales points so a wide range cannot balloon the response. */
    private const MAX_SERIES_DAYS = 366;

    /** Default look-ahead for the payment due cards, in days. */
    private const DEFAULT_DUE_WINDOW_DAYS = 7;

    /** Rows a card feed returns unless the caller asks for more. */
    private const DEFAULT_CARD_LIMIT = 10;

    public function __construct(
        private readonly ReportService $reports,
        private readonly BusinessSettingsService $businessSettings,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $companyId = (int) $data['company_id'];

        $ary = Invoice::where('company_id', $companyId)->whereIn('type', ['invoice', 'credit_note']);
        $apy = Invoice::where('company_id', $companyId)->whereIn('type', ['bill', 'debit_note']);

        $income = (float) (clone $ary)->whereIn('status', self::COUNTED_STATUSES)->sum('total');

        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        // The date filter scopes the card block only. The month_* fields below
        // stay pinned to the current month so existing consumers keep their
        // meaning; they default to the same window.
        $from = $data['from'] ?? $monthStart;
        $to = $data['to'] ?? $today;

        $pnl = $this->reports->profitAndLoss($companyId, $monthStart, $today);

        $outstandingReceivables = round((float) $ary->sum('balance_due'), 2);
        $outstandingPayables = round((float) $apy->sum('balance_due'), 2);

        $periodExpense = round((float) $this->reports->profitAndLoss($companyId, $from, $to)['total_expenses'], 2);

        $todayProfit = round((float) $this->reports->profitAndLoss($companyId, $today, $today)['net_income'], 2);

        $periodSales = $this->periodTotal($companyId, ['invoice'], $from, $to);

        $customerPayments = (float) Payment::where('company_id', $companyId)
            ->where('type', 'receipt')
            ->where('status', 'completed')
            ->sum('amount');
        $vendorPayments = (float) Payment::where('company_id', $companyId)
            ->where('type', 'payment')
            ->where('status', 'completed')
            ->sum('amount');

        return response()->json([
            'company_id' => $companyId,
            'as_of' => $today,
            'total_invoiced' => round($income, 2),
            'outstanding_receivables' => $outstandingReceivables,
            'outstanding_payables' => $outstandingPayables,
            'month_revenue' => round((float) $pnl['total_revenue'], 2),
            'month_expense' => round((float) $pnl['total_expenses'], 2),
            'month_net_profit' => round((float) $pnl['net_income'], 2),

            // Filtered card block: every field below honours from/to.
            'period' => ['from' => $from, 'to' => $to],
            'total_sales' => $periodSales,
            'total_sell_return' => $this->periodTotal($companyId, ['credit_note'], $from, $to),
            'total_purchase' => $this->periodTotal($companyId, ['bill'], $from, $to),
            'total_purchase_return' => $this->periodTotal($companyId, ['debit_note'], $from, $to),
            'expense' => $periodExpense,
            // Net follows the POS card: sales less money still owed plus spend.
            'net' => round($periodSales - $outstandingReceivables - $periodExpense, 2),
            'todays_profit' => $todayProfit,
            'daily_sales' => $this->dailySales($companyId, $from, $to),

            'total_clients' => Contact::where('company_id', $companyId)
                ->whereIn('type', ['customer', 'both'])->count(),
            'total_vendors' => Contact::where('company_id', $companyId)
                ->whereIn('type', ['supplier', 'both'])->count(),
            'total_customer_payment' => round($customerPayments, 2),
            'total_vendor_payment' => round($vendorPayments, 2),
            'open_sales_orders' => SalesOrder::where('company_id', $companyId)
                ->whereIn('status', ['draft', 'confirmed', 'processing'])
                ->count(),
            'low_stock_products' => Product::where('company_id', $companyId)
                ->where('type', 'product')
                ->whereNotNull('min_stock_level')
                ->whereColumn('stock_quantity', '<=', 'min_stock_level')
                ->count(),
            'total_products' => Product::where('company_id', $companyId)->count(),
            'recent_revenue' => round((float) $this->reports->profitAndLoss(
                $companyId,
                now()->subDays(30)->toDateString(),
                $today
            )['total_revenue'], 2),
            'monthly_customer_payments' => $this->monthlyPayments($companyId, 'receipt'),
            'monthly_vendor_payments' => $this->monthlyPayments($companyId, 'payment'),
            'recent_revenues' => $this->recentAccountActivity($companyId, 'revenue'),
            'recent_expenses' => $this->recentAccountActivity($companyId, 'expense'),
        ]);
    }

    /**
     * "Sales Current Financial Year" card: a twelve point monthly series for
     * the financial year that contains today, with returns kept beside gross
     * sales so the chart can plot either line.
     */
    public function financialYearSales(Request $request): JsonResponse
    {
        $companyId = $this->validateCompanyId($request);
        $startMonth = $this->financialYearStartMonth($companyId);

        $today = now();
        $startYear = $today->month >= $startMonth ? $today->year : $today->year - 1;
        $start = Carbon::parse(sprintf('%04d-%02d-01', $startYear, $startMonth));
        $end = $start->copy()->addMonths(12)->subDay();

        $rows = Invoice::where('company_id', $companyId)
            ->whereIn('type', ['invoice', 'credit_note'])
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->get(['issue_date', 'type', 'total']);

        // Bucket by month offset from the financial year start so a year that
        // straddles two calendar years still lines up with its own month order.
        $buckets = [];

        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->copy()->addMonths($offset);

            $buckets[$offset] = [
                'month' => $month->format('M'),
                'date' => $month->toDateString(),
                'sales' => 0.0,
                'sell_return' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $issued = Carbon::parse($row->issue_date);
            $offset = ($issued->year - $start->year) * 12 + ($issued->month - $start->month);

            if (! isset($buckets[$offset])) {
                continue;
            }

            $key = $row->type === 'invoice' ? 'sales' : 'sell_return';

            $buckets[$offset][$key] += (float) $row->total;
        }

        $series = [];
        $totalSales = 0.0;
        $totalSellReturn = 0.0;

        foreach ($buckets as $bucket) {
            $sales = round($bucket['sales'], 2);
            $sellReturn = round($bucket['sell_return'], 2);

            $series[] = [
                'month' => $bucket['month'],
                'date' => $bucket['date'],
                'sales' => $sales,
                'sell_return' => $sellReturn,
                'net' => round($sales - $sellReturn, 2),
            ];

            $totalSales += $sales;
            $totalSellReturn += $sellReturn;
        }

        return response()->json([
            'company_id' => $companyId,
            'financial_year' => [
                'start_month' => $startMonth,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'label' => $startMonth === 1 ? (string) $startYear : $startYear.'/'.($startYear + 1),
            ],
            'total_sales' => round($totalSales, 2),
            'total_sell_return' => round($totalSellReturn, 2),
            'net_sales' => round($totalSales - $totalSellReturn, 2),
            'series' => $series,
        ]);
    }

    /**
     * "Sales Payment Due" card: unpaid customer invoices falling due inside the
     * look-ahead window, overdue ones included.
     */
    public function salesPaymentDue(Request $request): JsonResponse
    {
        return $this->paymentDue($request, ['invoice'], 'customer');
    }

    /**
     * "Purchase Payment Due" card: unpaid supplier documents falling due inside
     * the same look-ahead window.
     */
    public function purchasePaymentDue(Request $request): JsonResponse
    {
        return $this->paymentDue($request, ['bill', 'debit_note'], 'supplier');
    }

    /**
     * "Product Stock Alert" card: sellable products at or below their alert
     * quantity, least stock first so the emptiest shelves surface at the top.
     */
    public function stockAlert(Request $request): JsonResponse
    {
        $companyId = $this->validateCompanyId($request);
        $limit = $this->cardLimit($request);

        $query = Product::where('company_id', $companyId)
            ->where('type', 'product')
            ->where('is_active', true)
            ->whereNotNull('min_stock_level')
            ->whereColumn('stock_quantity', '<=', 'min_stock_level');

        $total = (clone $query)->count();

        $products = $query->orderBy('stock_quantity')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'unit', 'stock_quantity', 'min_stock_level']);

        return response()->json([
            'company_id' => $companyId,
            'total' => $total,
            'limit' => $limit,
            'data' => $products->map(fn (Product $product) => [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'current_stock' => (int) $product->stock_quantity,
                'alert_level' => (int) $product->min_stock_level,
                'shortfall' => (int) $product->min_stock_level - (int) $product->stock_quantity,
                'is_out_of_stock' => (int) $product->stock_quantity <= 0,
            ])->values()->all(),
        ]);
    }

    /**
     * "Sales Order" card: recent orders with the quantity still outstanding
     * after whatever has already been invoiced against them.
     */
    public function salesOrders(Request $request): JsonResponse
    {
        $companyId = $this->validateCompanyId($request);
        $limit = $this->cardLimit($request);

        $query = SalesOrder::where('company_id', $companyId);

        $total = (clone $query)->count();

        $orders = $query->with(['contact', 'warehouse', 'createdBy', 'items'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        // What each order actually delivered, keyed by sales_order_id so the
        // remaining quantity is derived in one query instead of one per order.
        $orderIds = $orders->modelKeys();

        $invoicedByOrder = $orderIds === []
            ? []
            : DB::table('invoice_items')
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->whereIn('invoices.sales_order_id', $orderIds)
                ->whereIn('invoices.type', ['invoice', 'credit_note'])
                ->where('invoices.status', '!=', 'cancelled')
                ->groupBy('invoices.sales_order_id')
                ->pluck('invoice_items.quantity', 'invoices.sales_order_id')
                ->map(fn ($quantity) => (float) $quantity)
                ->all();

        return response()->json([
            'company_id' => $companyId,
            'total' => $total,
            'limit' => $limit,
            'data' => $orders->map(function (SalesOrder $order) use ($invoicedByOrder) {
                $ordered = (float) $order->items->sum('quantity');
                $invoiced = $invoicedByOrder[$order->id] ?? 0.0;

                return [
                    'sales_order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_date' => $this->formatDate($order->order_date),
                    'delivery_date' => $this->formatDate($order->delivery_date),
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'location' => $order->warehouse?->name,
                    'customer' => $order->contact === null ? null : [
                        'id' => $order->contact->id,
                        'name' => $order->contact->full_name,
                        'phone' => $order->contact->phone,
                    ],
                    'ordered_quantity' => round($ordered, 2),
                    'invoiced_quantity' => round($invoiced, 2),
                    'quantity_remaining' => round(max(0.0, $ordered - $invoiced), 2),
                    'total' => round((float) $order->total, 2),
                    'balance_due' => round((float) $order->balance_due, 2),
                    'added_by' => $order->createdBy?->name,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * "Pending Shipments" card: sales invoices that have not been delivered
     * yet, newest first, with the responsible staff member when one is set.
     */
    public function pendingShipments(Request $request): JsonResponse
    {
        $companyId = $this->validateCompanyId($request);
        $limit = $this->cardLimit($request);

        $query = Invoice::where('company_id', $companyId)
            ->where('type', 'invoice')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereIn('shipping_status', Invoice::OPEN_SHIPPING_STATUSES);

        $total = (clone $query)->count();

        $invoices = $query->with(['contact', 'serviceStaff'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'company_id' => $companyId,
            'total' => $total,
            'limit' => $limit,
            'data' => $invoices->map(fn (Invoice $invoice) => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $this->formatDate($invoice->issue_date),
                'shipping_status' => $invoice->shipping_status,
                'shipped_at' => $this->formatDate($invoice->shipped_at),
                'tracking_number' => $invoice->tracking_number,
                'payment_status' => $invoice->status,
                'total' => round((float) $invoice->total, 2),
                'balance_due' => round((float) $invoice->balance_due, 2),
                'customer' => $invoice->contact === null ? null : [
                    'id' => $invoice->contact->id,
                    'name' => $invoice->contact->full_name,
                    'phone' => $invoice->contact->phone,
                ],
                'service_staff' => $invoice->serviceStaff?->name,
            ])->values()->all(),
        ]);
    }

    /**
     * "CRM" card: funnel and pipeline health in one payload — active leads,
     * pipeline value, conversion rate, the funnel by status, leads by source
     * and how the open deals are distributed across the stages.
     */
    public function crmSummary(Request $request): JsonResponse
    {
        $companyId = $this->validateCompanyId($request);
        $limit = $this->cardLimit($request);

        $leads = Lead::where('company_id', $companyId);
        $deals = Deal::where('company_id', $companyId);

        $totalLeads = (clone $leads)->count();
        $activeLeads = (clone $leads)->active()->count();
        $convertedLeads = (clone $leads)->where('status', Lead::STATUS_CONVERTED)->count();
        $lostLeads = (clone $leads)->where('status', Lead::STATUS_LOST)->count();

        $leadStatusCounts = $this->leadStatusCounts($companyId);
        $leadSourceCounts = $this->leadSourceCounts($companyId);

        $openDeals = (clone $deals)->where('status', Deal::STATUS_OPEN);
        $openDealCount = $openDeals->count();
        $pipelineValue = round((float) (clone $openDeals)->sum('value'), 2);

        $wonDeals = (clone $deals)->where('status', Deal::STATUS_WON);
        $wonDealCount = $wonDeals->count();
        $wonValue = round((float) $wonDeals->sum('value'), 2);
        $lostDealCount = (clone $deals)->where('status', Deal::STATUS_LOST)->count();

        // Conversion is won share of decided deals: open work never dilutes it.
        $decided = $wonDealCount + $lostDealCount;

        $overdueFollowUps = (clone $leads)
            ->active()
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now())
            ->count();

        $followUps = (clone $leads)
            ->active()
            ->whereNotNull('next_follow_up_at')
            ->with('assignedTo')
            ->orderBy('next_follow_up_at')
            ->limit($limit)
            ->get();

        $topDeals = $openDeals->with(['stage', 'contact'])
            ->orderByDesc('value')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'company_id' => $companyId,
            'limit' => $limit,
            'total_leads' => $totalLeads,
            'active_leads' => $activeLeads,
            'converted_leads' => $convertedLeads,
            'lost_leads' => $lostLeads,
            'overdue_follow_ups' => $overdueFollowUps,
            'open_deals' => $openDealCount,
            'pipeline_value' => $pipelineValue,
            'won_deals' => $wonDealCount,
            'won_value' => $wonValue,
            'lost_deals' => $lostDealCount,
            'conversion_rate' => $decided === 0 ? 0.0 : round($wonDealCount * 100 / $decided, 2),
            'lead_funnel' => $leadStatusCounts,
            'leads_by_source' => $leadSourceCounts,
            'pipeline_by_stage' => $this->pipelineByStage($companyId),
            'data' => [
                'follow_ups' => $followUps->map(fn (Lead $lead) => [
                    'lead_id' => $lead->id,
                    'name' => $lead->name,
                    'organization' => $lead->organization,
                    'status' => $lead->status,
                    'value' => round((float) $lead->value, 2),
                    'next_follow_up_at' => $lead->next_follow_up_at,
                    'assigned_to' => $lead->assignedTo?->name,
                ])->values()->all(),
                'top_deals' => $topDeals->map(fn (Deal $deal) => [
                    'deal_id' => $deal->id,
                    'title' => $deal->title,
                    'value' => round((float) $deal->value, 2),
                    'stage' => $deal->stage?->name,
                    'close_date' => $this->formatDate($deal->close_date),
                    'contact' => $deal->contact === null ? null : [
                        'id' => $deal->contact->id,
                        'name' => $deal->contact->full_name,
                    ],
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Shared implementation behind the two payment due cards.
     *
     * @param  list<string>  $types
     */
    private function paymentDue(Request $request, array $types, string $party): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'days' => 'nullable|integer|min:1|max:365',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $companyId = (int) $data['company_id'];
        $days = (int) ($data['days'] ?? self::DEFAULT_DUE_WINDOW_DAYS);
        $limit = (int) ($data['limit'] ?? self::DEFAULT_CARD_LIMIT);
        $horizon = now()->addDays($days)->toDateString();

        $query = Invoice::where('company_id', $companyId)
            ->whereIn('type', $types)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $horizon);

        $total = (clone $query)->count();
        $totalDue = round((float) (clone $query)->sum('balance_due'), 2);

        $documents = (clone $query)->with('contact')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $today = now()->startOfDay();

        return response()->json([
            'company_id' => $companyId,
            'window_days' => $days,
            'due_before' => $horizon,
            'total' => $total,
            'total_due' => $totalDue,
            'limit' => $limit,
            'data' => $documents->map(function (Invoice $document) use ($party, $today) {
                return [
                    'document_id' => $document->id,
                    'document_number' => $document->invoice_number,
                    'party' => $party,
                    'issue_date' => $this->formatDate($document->issue_date),
                    'due_date' => $this->formatDate($document->due_date),
                    'status' => $document->status,
                    'is_overdue' => $document->due_date !== null
                        && $document->due_date->lessThan($today),
                    'days_overdue' => $document->due_date === null
                        ? 0
                        : max(0, (int) $document->due_date->diffInDays($today)),
                    'total' => round((float) $document->total, 2),
                    'paid_amount' => round((float) $document->paid_amount, 2),
                    'balance_due' => round((float) $document->balance_due, 2),
                    'contact' => $document->contact === null ? null : [
                        'id' => $document->contact->id,
                        'name' => $document->contact->full_name,
                        'phone' => $document->contact->phone,
                        'mobile' => $document->contact->mobile,
                    ],
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Lead funnel, zero-filled across every status so the chart keeps its
     * category order even for statuses with no rows.
     *
     * @return list<array{status: string, count: int}>
     */
    private function leadStatusCounts(int $companyId): array
    {
        $counts = array_fill_keys(Lead::STATUSES, 0);

        $rows = Lead::where('company_id', $companyId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $status = (string) $row->getAttribute('status');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row->getAttribute('total');
            }
        }

        return array_map(
            fn (string $status): array => ['status' => $status, 'count' => $counts[$status]],
            array_keys($counts),
        );
    }

    /**
     * Leads per acquisition source, zero-filled in the documented order.
     *
     * @return list<array{source: string, count: int}>
     */
    private function leadSourceCounts(int $companyId): array
    {
        $counts = array_fill_keys(Lead::SOURCES, 0);

        $rows = Lead::where('company_id', $companyId)
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->get();

        foreach ($rows as $row) {
            $source = (string) $row->getAttribute('source');

            if (array_key_exists($source, $counts)) {
                $counts[$source] = (int) $row->getAttribute('total');
            }
        }

        return array_map(
            fn (string $source): array => ['source' => $source, 'count' => $counts[$source]],
            array_keys($counts),
        );
    }

    /**
     * Open deals spread across the ordered pipeline. One grouped query plus
     * one ordered stage read keeps this flat no matter how many deals exist.
     *
     * @return list<array{stage_id: int, stage: string, open_deals: int, pipeline_value: float}>
     */
    private function pipelineByStage(int $companyId): array
    {
        $aggregates = Deal::where('company_id', $companyId)
            ->where('status', Deal::STATUS_OPEN)
            ->groupBy('crm_stage_id')
            ->selectRaw('crm_stage_id, count(*) as open_deals, coalesce(sum(value), 0) as pipeline_value')
            ->get()
            ->keyBy('crm_stage_id');

        $stages = CrmStage::where('company_id', $companyId)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'name']);

        return array_values($stages->map(function (CrmStage $stage) use ($aggregates) {
            $row = $aggregates->get($stage->getKey());

            return [
                'stage_id' => (int) $stage->getKey(),
                'stage' => (string) $stage->name,
                'open_deals' => $row === null ? 0 : (int) $row->getAttribute('open_deals'),
                'pipeline_value' => round((float) ($row?->getAttribute('pipeline_value') ?? 0), 2),
            ];
        })->all());
    }

    private function validateCompanyId(Request $request): int
    {
        $data = $request->validate(['company_id' => 'required|exists:companies,id']);

        return (int) $data['company_id'];
    }

    private function cardLimit(Request $request): int
    {
        $data = $request->validate(['limit' => 'nullable|integer|min:1|max:100']);

        return (int) ($data['limit'] ?? self::DEFAULT_CARD_LIMIT);
    }

    /**
     * Render a date column as Y-m-d. SalesOrder carries no date casts, so its
     * columns arrive as raw strings while Invoice columns arrive as Carbon.
     */
    private function formatDate(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toDateString();
    }

    /**
     * Fiscal calendar start month for the company, falling back to January
     * when the business has not configured one.
     */
    private function financialYearStartMonth(int $companyId): int
    {
        $configured = $this->businessSettings->value($companyId, 'business', 'financial_year_start_month');
        $month = is_numeric($configured) ? (int) $configured : 1;

        return $month >= 1 && $month <= 12 ? $month : 1;
    }

    /**
     * Sum of counted documents of the given types issued inside the range.
     * Returns and purchases are kept separate so the UI can show them as
     * distinct cards rather than netting them into one number.
     *
     * @param  list<string>  $types
     */
    private function periodTotal(int $companyId, array $types, string $from, string $to): float
    {
        $total = Invoice::where('company_id', $companyId)
            ->whereIn('type', $types)
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereBetween('issue_date', [$from, $to])
            ->sum('total');

        return round((float) $total, 2);
    }

    /**
     * Daily net sales across the range, with credit notes subtracting from the
     * day they were issued. Empty days are emitted as zero so the chart keeps a
     * continuous x-axis, and the window is capped at MAX_SERIES_DAYS.
     *
     * @return list<array{date: string, total: float}>
     */
    private function dailySales(int $companyId, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($start->diffInDays($end) >= self::MAX_SERIES_DAYS) {
            $start = $end->copy()->subDays(self::MAX_SERIES_DAYS - 1);
        }

        $rows = Invoice::where('company_id', $companyId)
            ->whereIn('type', ['invoice', 'credit_note'])
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->get(['issue_date', 'type', 'total']);

        $totals = [];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addDay()) {
            $totals[$cursor->toDateString()] = 0.0;
        }

        foreach ($rows as $row) {
            $day = Carbon::parse($row->issue_date)->toDateString();

            if (! array_key_exists($day, $totals)) {
                continue;
            }

            $totals[$day] += $row->type === 'invoice' ? (float) $row->total : -(float) $row->total;
        }

        $series = [];

        foreach ($totals as $date => $total) {
            $series[] = ['date' => $date, 'total' => round($total, 2)];
        }

        return $series;
    }

    /**
     * Sum of completed payments for the given type across the last six months.
     */
    private function monthlyPayments(int $companyId, string $type): array
    {
        $series = [];

        foreach (range(5, 0) as $offset) {
            $month = now()->subMonths($offset);
            $rows = Payment::where('company_id', $companyId)
                ->where('type', $type)
                ->where('status', 'completed')
                ->whereBetween('payment_date', [
                    $month->startOfMonth()->toDateString(),
                    $month->endOfMonth()->toDateString(),
                ])
                ->selectRaw('sum(amount) as total')
                ->first();

            $series[] = [
                'month' => $month->format('M'),
                'total' => round((float) ($rows->total ?? 0), 2),
            ];
        }

        return $series;
    }

    /**
     * Latest postings on accounts of the given type, mirroring the
     * "recent revenues / recent expenses" activity lists.
     */
    private function recentAccountActivity(int $companyId, string $type): array
    {
        $lines = JournalEntry::where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->join('journal_entry_lines', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->where('chart_of_accounts.type', $type)
            ->orderByDesc('journal_entries.entry_date')
            ->limit(8)
            ->get([
                'journal_entries.id',
                'journal_entries.description',
                'journal_entries.entry_date',
                'chart_of_accounts.name as account_name',
                'chart_of_accounts.code as account_code',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
            ]);

        return $lines->map(function ($row) use ($type) {
            return [
                'id' => $row->id,
                'title' => $row->account_code.' — '.$row->account_name,
                'description' => $row->description,
                'amount' => round((float) ($type === 'revenue' ? $row->credit : $row->debit), 2),
                'date' => $row->entry_date,
            ];
        })->values()->take(5)->all();
    }
}
