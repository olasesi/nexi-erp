<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    /**
     * Default chart of accounts installed for every new company.
     * Key = default account code, value = [name, type, normal_balance].
     */
    public const DEFAULT_ACCOUNTS = [
        '1010' => ['Cash and Cash Equivalents', 'asset', 'debit'],
        '1100' => ['Accounts Receivable', 'asset', 'debit'],
        '1200' => ['Inventory', 'asset', 'debit'],
        '1300' => ['Prepaid Expenses', 'asset', 'debit'],
        '1500' => ['Fixed Assets', 'asset', 'debit'],
        '1510' => ['Accumulated Depreciation', 'asset', 'credit'],
        '2010' => ['Accounts Payable', 'liability', 'credit'],
        '2100' => ['Sales Tax Payable', 'liability', 'credit'],
        '2130' => ['Payroll Taxes Payable', 'liability', 'credit'],
        '2200' => ['Short-term Loans', 'liability', 'credit'],
        '3010' => ['Owner\'s Equity', 'equity', 'credit'],
        '3020' => ['Retained Earnings', 'equity', 'credit'],
        '4010' => ['Sales Revenue', 'revenue', 'credit'],
        '4020' => ['Service Revenue', 'revenue', 'credit'],
        '4030' => ['Other Income', 'revenue', 'credit'],
        '5010' => ['Cost of Goods Sold', 'expense', 'debit'],
        '5005' => ['Purchases', 'expense', 'debit'],
        '5100' => ['Salaries and Wages', 'expense', 'debit'],
        '5200' => ['Rent Expense', 'expense', 'debit'],
        '5300' => ['Utilities Expense', 'expense', 'debit'],
        '5400' => ['Office Supplies Expense', 'expense', 'debit'],
        '5500' => ['Marketing and Advertising', 'expense', 'debit'],
        '5600' => ['Bank Service Charges', 'expense', 'debit'],
        '5700' => ['Other Expenses', 'expense', 'debit'],
    ];

    /**
     * Create the standard chart of accounts for a company, if missing.
     */
    public function ensureDefaultAccounts(int $companyId): void
    {
        foreach (self::DEFAULT_ACCOUNTS as $code => [$name, $type, $balance]) {
            ChartOfAccount::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $balance,
                    'is_system' => true,
                ]
            );
        }
    }

    public function findAccount(int $companyId, string $code): ?ChartOfAccount
    {
        return ChartOfAccount::where('company_id', $companyId)
            ->where('code', $code)
            ->first();
    }

    public function getOrCreateAccount(int $companyId, string $code, string $name, string $type = 'expense'): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['company_id' => $companyId, 'code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'normal_balance' => in_array($type, ['asset', 'expense']) ? 'debit' : 'credit',
            ]
        );
    }

    /**
     * Post a balanced double-entry journal entry.
     *
     * @param  array<int, array{account_code?: string, chart_of_account_id?: int,
     *                               debit?: mixed, credit?: mixed, description?: string|null}>  $lines
     */
    public function post(
        int $companyId,
        string $entryDate,
        ?string $description,
        array $lines,
        mixed $source = null,
        ?int $createdBy = null,
        string $status = 'posted',
    ): JournalEntry {
        $this->ensureDefaultAccounts($companyId);

        $normalized = $this->normalizeLines($companyId, $lines);

        $totalDebit = round(array_sum(array_column($normalized, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($normalized, 'credit')), 2);

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new \InvalidArgumentException(
                "Journal entry is out of balance: debits {$totalDebit} vs credits {$totalCredit}."
            );
        }

        if ($totalDebit <= 0) {
            throw new \InvalidArgumentException('Journal entry must contain at least one posting line.');
        }

        $entry = DB::transaction(function () use ($companyId, $entryDate, $description, $source, $createdBy, $status, $normalized) {
            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_number' => $this->nextEntryNumber($companyId),
                'description' => $description,
                'entry_date' => $entryDate,
                'status' => $status,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->id,
                'created_by' => $createdBy,
                'posted_at' => $status === 'posted' ? now() : null,
            ]);

            $entry->lines()->createMany($normalized);

            return $entry->load('lines');
        });

        return $entry;
    }

    /**
     * Void every posted journal entry referencing the given source model.
     */
    public function voidForSource(mixed $source): void
    {
        JournalEntry::where('source_type', get_class($source))
            ->where('source_id', $source->id)
            ->where('status', 'posted')
            ->update(['status' => 'void']);
    }

    public function normalizeLines(int $companyId, array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException('A journal line cannot be both a debit and a credit.');
            }

            if ($debit === 0.0 && $credit === 0.0) {
                throw new \InvalidArgumentException('A journal line must contain a debit or a credit amount.');
            }

            if (isset($line['chart_of_account_id'])) {
                $account = ChartOfAccount::find($line['chart_of_account_id']);
            } else {
                $account = $this->findAccount($companyId, $line['account_code'] ?? '');
            }

            if (! $account) {
                throw new \InvalidArgumentException(
                    'Unknown account: '.($line['account_code'] ?? $line['chart_of_account_id'] ?? 'null').'.'
                );
            }

            $normalized[] = [
                'chart_of_account_id' => $account->id,
                'description' => $line['description'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        return $normalized;
    }

    protected function nextEntryNumber(int $companyId): string
    {
        $max = JournalEntry::where('company_id', $companyId)->max('id') ?? 0;

        return 'JE-'.now()->format('Ymd').'-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
