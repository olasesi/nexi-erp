<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BusinessHealthSnapshot;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rule based business health scoring.
 *
 * Every number below is read from the tables this ERP already maintains, and
 * a category with no supporting data reports `score = null` instead of a
 * misleading zero: the overall score is the weighted mean of the categories
 * that do have data. No category is invented where the product has no data
 * (projects, for example, has no module yet).
 *
 * `generated_by` is `rules` everywhere today; the snapshot shape is the same
 * one an LLM pass would fill in, so swapping the generator later does not
 * move the API or the table.
 *
 * @phpstan-type Category array{label: string, score: int|null, weight: float, signals: array<string, float>, note: string|null}
 * @phpstan-type Alert array{key: string, severity: string, domain: string, title: string, detail: string}
 * @phpstan-type Insight array{key: string, domain: string, text: string}
 * @phpstan-type Recommendation array{domain: string, severity: string, title: string, action: string}
 */
class BusinessHealthService
{
    /**
     * Categories that contribute to the overall score, and how much each
     * matters. `projects` is intentionally absent: see categories().
     *
     * @var array<string, float>
     */
    public const CATEGORY_WEIGHTS = [
        'financial' => 30.0,
        'sales' => 30.0,
        'operations' => 20.0,
        'team' => 20.0,
    ];

    /** Invoice statuses that count towards revenue (mirrors the dashboard). */
    private const COUNTED_STATUSES = ['paid', 'partial', 'sent', 'overdue'];

    /** Sales order statuses that still represent open work. */
    private const OPEN_SALES_ORDER_STATUSES = ['draft', 'confirmed', 'processing'];

    private const WINDOW_DAYS = 30;

    /**
     * A business health score needs more than one corner of the business to
     * describe it: below this many scored categories the overall stays null
     * instead of letting a lone active user account report 100.
     */
    private const MIN_SCORED_CATEGORIES = 2;

    /**
     * Full report for one company: scores, facts, trend, alerts, insights and
     * the recommendations derived from them.
     *
     * @phpstan-return array{
     *     company_id: int,
     *     score_date: string,
     *     generated_by: string,
     *     overall_score: int|null,
     *     categories: array<string, Category>,
     *     coverage: array{scored: int, total: int},
     *     signals: array<string, array<string, float>>,
     *     trend: array{
     *         points: array<int, array{score_date: string, overall_score: int|null, financial_score: int|null, sales_score: int|null, operations_score: int|null, team_score: int|null}>,
     *         delta: int|null
     *     },
     *     alerts: list<Alert>,
     *     insights: list<Insight>,
     *     recommendations: list<Recommendation>
     * }
     */
    public function report(int $companyId): array
    {
        $analysis = $this->analysis($companyId);
        $categories = $analysis['categories'];
        $trend = $this->trend($companyId);
        $overall = $this->overall($categories);

        return [
            'company_id' => $companyId,
            'score_date' => now()->toDateString(),
            'generated_by' => 'rules',
            'overall_score' => $overall,
            'categories' => $categories,
            'coverage' => $this->coverage($categories),
            'signals' => array_map(
                static fn (array $category): array => $category['signals'],
                $categories
            ),
            'trend' => [
                'points' => $trend,
                'delta' => $this->delta($companyId, $overall),
            ],
            'alerts' => $analysis['alerts'],
            'insights' => $analysis['insights'],
            'recommendations' => $analysis['recommendations'],
        ];
    }

    /**
     * Score the company and persist the day's row, notifying the company
     * about critical alerts once per day. Called by `advisor:snapshot`.
     */
    public function record(int $companyId): BusinessHealthSnapshot
    {
        $analysis = $this->analysis($companyId);
        $categories = $analysis['categories'];

        $scoreDate = now()->toDateString();
        $data = [
            'company_id' => $companyId,
            'score_date' => $scoreDate,
            'overall_score' => $this->overall($categories),
            'financial_score' => $categories['financial']['score'],
            'sales_score' => $categories['sales']['score'],
            'operations_score' => $categories['operations']['score'],
            'team_score' => $categories['team']['score'],
            'projects_score' => $categories['projects']['score'],
            'signals' => array_map(
                static fn (array $category): array => $category['signals'],
                $categories
            ),
            'alerts' => $analysis['alerts'],
            'insights' => $analysis['insights'],
            'recommendations' => $analysis['recommendations'],
            'generated_by' => 'rules',
        ];

        $snapshot = BusinessHealthSnapshot::query()
            ->where('company_id', $companyId)
            ->whereDate('score_date', $scoreDate)
            ->first();

        if ($snapshot === null) {
            $snapshot = new BusinessHealthSnapshot;
        }

        $snapshot->fill($data)->save();

        $this->notify($companyId, $analysis['alerts']);

        return $snapshot;
    }

    /**
     * @phpstan-return array{facts: array<string, float>, categories: array<string, Category>, alerts: list<Alert>, insights: list<Insight>, recommendations: list<Recommendation>}
     */
    private function analysis(int $companyId): array
    {
        $facts = $this->facts($companyId);
        $categories = $this->categories($facts);
        $alerts = $this->alerts($facts);

        return [
            'facts' => $facts,
            'categories' => $categories,
            'alerts' => $alerts,
            'insights' => $this->insights($facts, $companyId),
            'recommendations' => $this->recommendations($facts, $alerts),
        ];
    }

    /**
     * Raw facts. Every value is a float so downstream arithmetic is
     * type-safe; absence of data is expressed by all-zero facts rather than
     * nulls, and is detected per category from those zeros.
     *
     * @return array<string, float>
     */
    private function facts(int $companyId): array
    {
        $today = now()->startOfDay();
        $windowFrom = $today->copy()->subDays(self::WINDOW_DAYS - 1)->toDateString();
        $windowTo = $today->toDateString();
        $last7From = $today->copy()->subDays(6)->toDateString();
        $prev7From = $today->copy()->subDays(13)->toDateString();
        $prev7To = $today->copy()->subDays(7)->toDateString();

        $sales = Invoice::where('company_id', $companyId)
            ->where('type', 'invoice')
            ->whereIn('status', self::COUNTED_STATUSES);

        $returns = Invoice::where('company_id', $companyId)
            ->where('type', 'credit_note')
            ->whereIn('status', self::COUNTED_STATUSES);

        $receivable = Invoice::where('company_id', $companyId)
            ->whereIn('type', ['invoice', 'credit_note'])
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('balance_due', '>', 0);

        $overdue = (clone $receivable)
            ->where('type', 'invoice')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $windowTo);

        $products = Product::where('company_id', $companyId)->where('type', 'product');
        $users = User::where('company_id', $companyId);

        return [
            'revenue_30' => (float) (clone $sales)
                ->whereDate('issue_date', '>=', $windowFrom)->whereDate('issue_date', '<=', $windowTo)->sum('total'),
            'returns_30' => (float) (clone $returns)
                ->whereDate('issue_date', '>=', $windowFrom)->whereDate('issue_date', '<=', $windowTo)->sum('total'),
            'income_30' => (float) Income::where('company_id', $companyId)
                ->whereDate('date', '>=', $windowFrom)->whereDate('date', '<=', $windowTo)->sum('amount'),
            'expenses_30' => (float) Expense::where('company_id', $companyId)
                ->whereDate('date', '>=', $windowFrom)->whereDate('date', '<=', $windowTo)->sum('amount'),
            'revenue_last7' => (float) (clone $sales)
                ->whereDate('issue_date', '>=', $last7From)->whereDate('issue_date', '<=', $windowTo)->sum('total'),
            'revenue_prev7' => (float) (clone $sales)
                ->whereDate('issue_date', '>=', $prev7From)->whereDate('issue_date', '<=', $prev7To)->sum('total'),
            'expenses_last7' => (float) Expense::where('company_id', $companyId)
                ->whereDate('date', '>=', $last7From)->whereDate('date', '<=', $windowTo)->sum('amount'),
            'expenses_prev7' => (float) Expense::where('company_id', $companyId)
                ->whereDate('date', '>=', $prev7From)->whereDate('date', '<=', $prev7To)->sum('amount'),
            'invoiced_all' => (float) (clone $sales)->sum('total'),
            'outstanding' => (float) (clone $receivable)->sum('balance_due'),
            'overdue_count' => (float) (clone $overdue)->count(),
            'overdue_amount' => (float) (clone $overdue)->sum('balance_due'),

            'won_deals' => (float) Deal::where('company_id', $companyId)->where('status', Deal::STATUS_WON)->count(),
            'lost_deals' => (float) Deal::where('company_id', $companyId)->where('status', Deal::STATUS_LOST)->count(),
            'open_deals' => (float) Deal::where('company_id', $companyId)->where('status', Deal::STATUS_OPEN)->count(),
            'open_deal_value' => (float) Deal::where('company_id', $companyId)->where('status', Deal::STATUS_OPEN)->sum('value'),
            'total_deals' => (float) Deal::where('company_id', $companyId)->count(),
            'active_leads' => (float) Lead::where('company_id', $companyId)->active()->count(),

            'total_products' => (float) $products->count(),
            'stock_out' => (float) (clone $products)->where('is_active', true)->where('stock_quantity', '<=', 0)->count(),
            'stock_low' => (float) (clone $products)->where('is_active', true)->whereNotNull('min_stock_level')
                ->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock_level')->count(),
            'open_sales_orders' => (float) SalesOrder::where('company_id', $companyId)
                ->whereIn('status', self::OPEN_SALES_ORDER_STATUSES)->count(),
            'total_sales_orders' => (float) SalesOrder::where('company_id', $companyId)->count(),

            'users_total' => (float) $users->count(),
            'users_active' => (float) (clone $users)->where('is_active', true)->count(),
            'contributors_7d' => (float) AuditLog::where('company_id', $companyId)
                ->where('created_at', '>=', $last7From)->distinct()->count('user_id'),
            'audit_events_7d' => (float) AuditLog::where('company_id', $companyId)
                ->where('created_at', '>=', $last7From)->count(),
        ];
    }

    /**
     * Score every category. A null score means "no data", which excludes the
     * category from the overall mean instead of dragging it down.
     *
     * @param  array<string, float>  $f
     * @return array<string, Category>
     */
    private function categories(array $f): array
    {
        return [
            'financial' => $this->category('Financial', $this->scoreFinancial($f), self::CATEGORY_WEIGHTS['financial']),
            'sales' => $this->category('Sales', $this->scoreSales($f), self::CATEGORY_WEIGHTS['sales']),
            'operations' => $this->category('Operations', $this->scoreOperations($f), self::CATEGORY_WEIGHTS['operations']),
            'team' => $this->category('Team', $this->scoreTeam($f), self::CATEGORY_WEIGHTS['team']),
            'projects' => [
                'label' => 'Projects',
                'score' => null,
                'weight' => 0.0,
                'signals' => [],
                'note' => 'No project data is tracked yet, so this category is excluded from the score.',
            ],
        ];
    }

    /**
     * @param  array<string, float>  $f
     * @return array{score: int|null, signals: array<string, float>}
     */
    private function scoreFinancial(array $f): array
    {
        $inflow = $f['revenue_30'] + $f['income_30'];
        $net = $inflow - $f['returns_30'] - $f['expenses_30'];

        $margin = null;
        if ($inflow > 0) {
            $margin = $net / $inflow;
        } elseif ($f['expenses_30'] > 0) {
            $margin = -1.0;
        }

        $result = $this->weighted([
            'margin' => [$margin === null ? null : $this->scale($margin, -0.3, 0.5), 3.0],
            'receivables' => $f['invoiced_all'] > 0
                ? [100 * (1 - $this->clamp01($f['outstanding'] / $f['invoiced_all'])), 2.0]
                : [null, 2.0],
            'expense_discipline' => $inflow > 0
                ? [100 * (1 - $this->clamp01($f['expenses_30'] / $inflow)), 1.0]
                : ($f['expenses_30'] > 0 ? [0.0, 1.0] : [null, 1.0]),
        ]);

        if (($inflow + $f['expenses_30'] + $f['invoiced_all']) <= 0) {
            $result['score'] = null;
        }

        return $result;
    }

    /**
     * @param  array<string, float>  $f
     * @return array{score: int|null, signals: array<string, float>}
     */
    private function scoreSales(array $f): array
    {
        $decided = $f['won_deals'] + $f['lost_deals'];

        $trend = null;
        if ($f['revenue_prev7'] > 0) {
            $trend = 100 * $this->clamp01((($f['revenue_last7'] / $f['revenue_prev7']) + 1) / 3);
        } elseif ($f['revenue_last7'] > 0) {
            $trend = 80.0;
        }

        $result = $this->weighted([
            'conversion' => $decided > 0 ? [100 * ($f['won_deals'] / $decided), 2.0] : [null, 2.0],
            'revenue_trend' => [$trend, 2.0],
            'open_pipeline' => $f['total_deals'] > 0 ? [100 * $this->clamp01($f['open_deals'] / 5), 1.0] : [null, 1.0],
        ]);

        if (($f['invoiced_all'] + $f['total_deals']) <= 0) {
            $result['score'] = null;
        }

        return $result;
    }

    /**
     * @param  array<string, float>  $f
     * @return array{score: int|null, signals: array<string, float>}
     */
    private function scoreOperations(array $f): array
    {
        $result = $this->weighted([
            'stock_health' => $f['total_products'] > 0
                ? [100 * (1 - $this->clamp01(($f['stock_out'] + 0.5 * $f['stock_low']) / $f['total_products'])), 2.0]
                : [null, 2.0],
            'order_flow' => $f['total_sales_orders'] > 0
                ? [100 * (1 - $this->clamp01($f['open_sales_orders'] / $f['total_sales_orders'])), 1.0]
                : [null, 1.0],
        ]);

        if (($f['total_products'] + $f['total_sales_orders']) <= 0) {
            $result['score'] = null;
        }

        return $result;
    }

    /**
     * @param  array<string, float>  $f
     * @return array{score: int|null, signals: array<string, float>}
     */
    private function scoreTeam(array $f): array
    {
        $result = $this->weighted([
            'active_staff' => $f['users_total'] > 0
                ? [100 * ($f['users_active'] / $f['users_total']), 2.0]
                : [null, 2.0],
            'weekly_participation' => $f['audit_events_7d'] > 0 && $f['users_active'] > 0
                ? [100 * $this->clamp01($f['contributors_7d'] / $f['users_active']), 1.0]
                : [null, 1.0],
        ]);

        if ($f['users_total'] <= 0) {
            $result['score'] = null;
        }

        return $result;
    }

    /**
     * @param  array{score: int|null, signals: array<string, float>}  $result
     * @return Category
     */
    private function category(string $label, array $result, float $weight): array
    {
        return [
            'label' => $label,
            'score' => $result['score'],
            'weight' => $weight,
            'signals' => $result['signals'],
            'note' => $result['score'] === null ? 'No data for this category yet.' : null,
        ];
    }

    /**
     * Weighted mean over the categories that have data, or null until enough
     * of the business is represented for the number to mean anything.
     *
     * @param  array<string, Category>  $categories
     */
    private function overall(array $categories): ?int
    {
        $sum = 0.0;
        $weight = 0.0;
        $scored = 0;

        foreach (self::CATEGORY_WEIGHTS as $key => $categoryWeight) {
            $score = $categories[$key]['score'] ?? null;

            if ($score === null) {
                continue;
            }

            $scored++;
            $sum += $score * $categoryWeight;
            $weight += $categoryWeight;
        }

        if ($scored < self::MIN_SCORED_CATEGORIES || $weight === 0.0) {
            return null;
        }

        return (int) round($sum / $weight);
    }

    /**
     * How much of the weighted surface actually contributed to the score.
     *
     * @param  array<string, Category>  $categories
     * @return array{scored: int, total: int}
     */
    private function coverage(array $categories): array
    {
        $scored = 0;

        foreach (array_keys(self::CATEGORY_WEIGHTS) as $key) {
            if (($categories[$key]['score'] ?? null) !== null) {
                $scored++;
            }
        }

        return ['scored' => $scored, 'total' => count(self::CATEGORY_WEIGHTS)];
    }

    /**
     * @param  array<string, array{0: float|null, 1: float}>  $components
     * @return array{score: int|null, signals: array<string, float>}
     */
    private function weighted(array $components): array
    {
        $sum = 0.0;
        $weight = 0.0;
        $signals = [];

        foreach ($components as $key => [$score, $componentWeight]) {
            if ($score === null) {
                continue;
            }

            $signals[$key] = round($score, 1);
            $sum += $score * $componentWeight;
            $weight += $componentWeight;
        }

        return [
            'score' => $weight > 0 ? (int) round($sum / $weight) : null,
            'signals' => $signals,
        ];
    }

    /**
     * Threshold breaches worth a human's attention. Only fires when the
     * underlying data exists, so an empty company reports no alerts rather
     * than a wall of red.
     *
     * @param  array<string, float>  $f
     * @return list<Alert>
     */
    private function alerts(array $f): array
    {
        $alerts = [];

        if ($f['stock_out'] > 0) {
            $alerts[] = [
                'key' => 'stock_out',
                'severity' => 'critical',
                'domain' => 'operations',
                'title' => (int) $f['stock_out'].' product'.($f['stock_out'] === 1.0 ? '' : 's').' out of stock',
                'detail' => 'Selling these will fail or oversell until they are restocked.',
            ];
        }

        if ($f['stock_low'] > 0) {
            $alerts[] = [
                'key' => 'stock_low',
                'severity' => 'warning',
                'domain' => 'operations',
                'title' => (int) $f['stock_low'].' product'.($f['stock_low'] === 1.0 ? '' : 's').' at or below minimum stock',
                'detail' => 'Reorder before these run out.',
            ];
        }

        if ($f['overdue_count'] > 0) {
            $alerts[] = [
                'key' => 'overdue_invoices',
                'severity' => $f['overdue_count'] >= 5 ? 'critical' : 'warning',
                'domain' => 'financial',
                'title' => (int) $f['overdue_count'].' invoice'.($f['overdue_count'] === 1.0 ? '' : 's').' past due',
                'detail' => $this->money($f['overdue_amount']).' is overdue and still outstanding.',
            ];
        }

        $inflow = $f['revenue_30'] + $f['income_30'];
        $net = $inflow - $f['returns_30'] - $f['expenses_30'];

        if ($inflow > 0 && $net < 0) {
            $alerts[] = [
                'key' => 'cash_flow',
                'severity' => $net < -$inflow ? 'critical' : 'warning',
                'domain' => 'financial',
                'title' => 'Spending exceeded income this month',
                'detail' => $this->money(abs($net)).' more went out than came in over the last '
                    .self::WINDOW_DAYS.' days.',
            ];
        }

        if ($f['expenses_prev7'] > 0 && $f['expenses_last7'] > 1.5 * $f['expenses_prev7']) {
            $alerts[] = [
                'key' => 'expense_spike',
                'severity' => 'warning',
                'domain' => 'financial',
                'title' => 'Expenses rose sharply this week',
                'detail' => $this->money($f['expenses_last7']).' spent in the last 7 days against '
                    .$this->money($f['expenses_prev7']).' the week before.',
            ];
        }

        $decided = $f['won_deals'] + $f['lost_deals'];
        if ($decided >= 5 && $f['won_deals'] / $decided < 0.3) {
            $alerts[] = [
                'key' => 'low_conversion',
                'severity' => 'warning',
                'domain' => 'sales',
                'title' => 'Deal win rate is below 30%',
                'detail' => (int) $f['won_deals'].' of '.(int) $decided.' decided deals were won.',
            ];
        }

        if ($f['users_total'] > 0 && $f['audit_events_7d'] === 0.0) {
            $alerts[] = [
                'key' => 'no_activity',
                'severity' => 'info',
                'domain' => 'team',
                'title' => 'No recorded activity this week',
                'detail' => 'Nothing has been logged in the audit trail for the last 7 days.',
            ];
        }

        return $alerts;
    }

    /**
     * Factual statements about the current position, each backed by a number
     * the reader can go and verify.
     *
     * @param  array<string, float>  $f
     * @return list<Insight>
     */
    private function insights(array $f, int $companyId): array
    {
        $insights = [];

        $inflow = $f['revenue_30'] + $f['income_30'];
        $net = $inflow - $f['returns_30'] - $f['expenses_30'];

        if ($inflow > 0 || $f['expenses_30'] > 0) {
            $margin = $inflow > 0 ? round(100 * $net / $inflow, 1) : null;

            $insights[] = [
                'key' => 'net_result',
                'domain' => 'financial',
                'text' => 'Last '.self::WINDOW_DAYS.' days: '.$this->money($inflow).' in, '
                    .$this->money($f['expenses_30']).' out, net '.$this->money($net)
                    .($margin === null ? '' : ' ('.$margin.'% margin).'),
            ];
        }

        if ($f['invoiced_all'] > 0) {
            $insights[] = [
                'key' => 'receivables',
                'domain' => 'financial',
                'text' => $this->money($f['outstanding']).' is still owed across all invoices ('
                    .round(100 * $f['outstanding'] / $f['invoiced_all'], 1).'% of everything billed)'
                    .($f['overdue_count'] > 0
                        ? ', with '.(int) $f['overdue_count'].' overdue worth '.$this->money($f['overdue_amount'])
                        : ''),
            ];
        }

        if ($f['revenue_prev7'] > 0 || $f['revenue_last7'] > 0) {
            $change = $f['revenue_prev7'] > 0
                ? round(100 * ($f['revenue_last7'] - $f['revenue_prev7']) / $f['revenue_prev7'], 1)
                : null;

            $insights[] = [
                'key' => 'sales_trend',
                'domain' => 'sales',
                'text' => 'Sales of '.$this->money($f['revenue_last7']).' in the last 7 days'
                    .($change === null
                        ? ', up from nothing the week before.'
                        : ', '.($change >= 0 ? 'up ' : 'down ').abs($change).'% on the previous week.'),
            ];
        }

        if ($f['total_deals'] > 0) {
            $decided = $f['won_deals'] + $f['lost_deals'];

            $insights[] = [
                'key' => 'pipeline',
                'domain' => 'sales',
                'text' => (int) $f['open_deals'].' open deal'.($f['open_deals'] === 1.0 ? '' : 's')
                    .' worth '.$this->money($f['open_deal_value'])
                    .($decided > 0
                        ? '; '.(int) $f['won_deals'].' of '.(int) $decided.' decided deals won.'
                        : '.'),
            ];
        }

        if ($f['total_products'] > 0) {
            $insights[] = [
                'key' => 'stock_position',
                'domain' => 'operations',
                'text' => (int) $f['total_products'].' products tracked, '
                    .(int) ($f['stock_out'] + $f['stock_low']).' at or below their alert level'
                    .($f['stock_out'] > 0 ? ' ('.(int) $f['stock_out'].' already out of stock)' : ''),
            ];
        }

        if ($f['users_total'] > 0) {
            $insights[] = [
                'key' => 'team_engagement',
                'domain' => 'team',
                'text' => (int) $f['users_active'].' of '.(int) $f['users_total']
                    .' staff accounts are active, and '.(int) $f['contributors_7d']
                    .' logged activity in the last 7 days.',
            ];
        }

        if ($top = $this->topExpenseCategory($f, $companyId)) {
            $insights[] = $top;
        }

        return $insights;
    }

    /**
     * Turn alerts and weak signals into concrete next steps. One
     * recommendation per problem, phrased as an action.
     *
     * @param  array<string, float>  $f
     * @param  list<Alert>  $alerts
     * @return list<Recommendation>
     */
    private function recommendations(array $f, array $alerts): array
    {
        /** @var array<string, string> $severity */
        $severity = array_column($alerts, 'severity', 'key');

        $recommendations = [];

        if (isset($severity['overdue_invoices'])) {
            $recommendations[] = [
                'domain' => 'financial',
                'severity' => $severity['overdue_invoices'],
                'title' => 'Collect what is already owed',
                'action' => 'Chase the '.(int) $f['overdue_count'].' overdue invoice'
                    .($f['overdue_count'] === 1.0 ? '' : 's').' worth '.$this->money($f['overdue_amount'])
                    .', and turn on payment reminders so it does not build up again.',
            ];
        }

        if (isset($severity['cash_flow'])) {
            $recommendations[] = [
                'domain' => 'financial',
                'severity' => 'critical',
                'title' => 'Close the spending gap',
                'action' => 'Review the largest expense categories for the last '
                    .self::WINDOW_DAYS.' days and pause anything that is not paying for itself.',
            ];
        }

        if (isset($severity['expense_spike'])) {
            $recommendations[] = [
                'domain' => 'financial',
                'severity' => 'warning',
                'title' => 'Investigate this week’s spending jump',
                'action' => 'Compare this week’s expenses against the previous week and check for '
                    .'duplicate or one-off entries.',
            ];
        }

        if (isset($severity['stock_out']) || isset($severity['stock_low'])) {
            $atRisk = $f['stock_out'] + $f['stock_low'];

            $recommendations[] = [
                'domain' => 'operations',
                'severity' => isset($severity['stock_out']) ? 'critical' : 'warning',
                'title' => 'Restock the shelves that are running dry',
                'action' => 'Raise purchase orders for the '.(int) $atRisk.' product'
                    .($atRisk === 1.0 ? '' : 's').' at or below their minimum stock level.',
            ];
        }

        if (isset($severity['low_conversion'])) {
            $recommendations[] = [
                'domain' => 'sales',
                'severity' => 'warning',
                'title' => 'Find out why deals are being lost',
                'action' => 'Review the lost deals for a common reason and tighten qualification '
                    .'before deals reach the pipeline.',
            ];
        }

        if ($f['total_deals'] > 0 && $f['open_deals'] === 0.0) {
            $recommendations[] = [
                'domain' => 'sales',
                'severity' => 'low',
                'title' => 'Refill the pipeline',
                'action' => 'There are no open deals. Work the '
                    .(int) $f['active_leads'].' active lead'.($f['active_leads'] === 1.0 ? '' : 's')
                    .' into opportunities.',
            ];
        }

        if (isset($severity['no_activity'])) {
            $recommendations[] = [
                'domain' => 'team',
                'severity' => 'low',
                'title' => 'Get the team logging their work',
                'action' => 'Nothing has been recorded for a week. Confirm the team is entering '
                    .'orders, expenses and changes so reporting stays trustworthy.',
            ];
        }

        return $recommendations;
    }

    /**
     * Largest expense category in the window, when one exists.
     *
     * @param  array<string, float>  $f
     * @return Insight|null
     */
    private function topExpenseCategory(array $f, int $companyId): ?array
    {
        if ($f['expenses_30'] <= 0) {
            return null;
        }

        $row = DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.company_id', $companyId)
            ->whereDate('expenses.date', '>=', now()->startOfDay()->subDays(self::WINDOW_DAYS - 1)->toDateString())
            ->whereDate('expenses.date', '<=', now()->toDateString())
            ->groupBy('expense_categories.name')
            ->selectRaw('expense_categories.name as name, sum(expenses.amount) as total')
            ->orderByDesc('total')
            ->first();

        if ($row === null || $row->name === null) {
            return null;
        }

        return [
            'key' => 'top_expense',
            'domain' => 'financial',
            'text' => 'Largest expense category in the last '.self::WINDOW_DAYS.' days: '
                .(string) $row->name.' at '.$this->money((float) $row->total).'.',
        ];
    }

    /**
     * Stored scores, oldest first, so a chart can plot the line directly.
     *
     * @return array<int, array{score_date: string, overall_score: int|null, financial_score: int|null, sales_score: int|null, operations_score: int|null, team_score: int|null}>
     */
    private function trend(int $companyId): array
    {
        return BusinessHealthSnapshot::where('company_id', $companyId)
            ->recent()
            ->limit(7)
            ->get()
            ->map(static fn (BusinessHealthSnapshot $snapshot): array => [
                'score_date' => $snapshot->score_date->toDateString(),
                'overall_score' => $snapshot->overall_score,
                'financial_score' => $snapshot->financial_score,
                'sales_score' => $snapshot->sales_score,
                'operations_score' => $snapshot->operations_score,
                'team_score' => $snapshot->team_score,
            ])
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * Change against the most recent stored score from a previous day, or
     * null while there is nothing to compare against.
     */
    private function delta(int $companyId, ?int $current): ?int
    {
        if ($current === null) {
            return null;
        }

        $previous = BusinessHealthSnapshot::where('company_id', $companyId)
            ->whereDate('score_date', '<', now()->toDateString())
            ->recent()
            ->value('overall_score');

        if ($previous === null) {
            return null;
        }

        return $current - (int) $previous;
    }

    /**
     * The first generator this ERP has for in-app notifications: critical
     * health alerts reach the whole company once per day, not once per page
     * view.
     *
     * @param  list<Alert>  $alerts
     */
    private function notify(int $companyId, array $alerts): void
    {
        foreach ($alerts as $alert) {
            if ($alert['severity'] !== 'critical') {
                continue;
            }

            $title = 'Business health: '.$alert['title'];

            $alreadySent = Notification::where('company_id', $companyId)
                ->where('type', 'business_advisor')
                ->where('title', $title)
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($alreadySent) {
                continue;
            }

            Notification::create([
                'company_id' => $companyId,
                'user_id' => null,
                'type' => 'business_advisor',
                'title' => $title,
                'body' => $alert['detail'],
                'data' => [
                    'key' => $alert['key'],
                    'domain' => $alert['domain'],
                    'severity' => $alert['severity'],
                ],
            ]);
        }
    }

    /**
     * Map a ratio onto 0-100 across an expected range, clamped at both ends.
     */
    private function scale(float $value, float $from, float $to): float
    {
        if ($from === $to) {
            return 0.0;
        }

        return 100 * $this->clamp01(($value - $from) / ($to - $from));
    }

    private function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    private function money(float $value): string
    {
        return number_format($value, 2);
    }
}
