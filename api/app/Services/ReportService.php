<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;

class ReportService
{
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

        $invoices = Invoice::where('company_id', $companyId)
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
                'type' => $invoice->type,
                'due_date' => $invoice->due_date?->toDateString(),
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
