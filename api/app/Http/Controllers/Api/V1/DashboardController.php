<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate(['company_id' => 'required|exists:companies,id']);

        $companyId = (int) $data['company_id'];

        $ary = Invoice::where('company_id', $companyId)->whereIn('type', ['invoice', 'credit_note']);
        $apy = Invoice::where('company_id', $companyId)->whereIn('type', ['bill', 'debit_note']);

        $income = (float) (clone $ary)->whereIn('status', ['paid', 'partial', 'sent', 'overdue'])->sum('total');

        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        $pnl = $this->reports->profitAndLoss($companyId, $monthStart, $today);

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
            'outstanding_receivables' => round((float) $ary->sum('balance_due'), 2),
            'outstanding_payables' => round((float) $apy->sum('balance_due'), 2),
            'month_revenue' => round((float) $pnl['total_revenue'], 2),
            'month_expense' => round((float) $pnl['total_expenses'], 2),
            'month_net_profit' => round((float) $pnl['net_income'], 2),
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
