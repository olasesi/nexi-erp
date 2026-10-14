<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Notifications\InvoiceOverdueNotification;
use App\Services\NotificationService;
use App\Services\WebhookService;
use Illuminate\Console\Command;

/**
 * Flips issued sales invoices that have passed their due date with a balance
 * still outstanding into the overdue state, broadcasting an `invoice.overdue`
 * webhook and raising an in-app notice per company.
 */
class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue
                            {--company= : Only process a single company}';

    protected $description = 'Mark past-due sales invoices as overdue and notify the company';

    public function handle(NotificationService $notifications): int
    {
        $company = $this->option('company');

        $query = Invoice::query()
            ->withoutGlobalScope('company_scope')
            ->where('type', 'invoice')
            ->whereIn('status', ['sent', 'partial'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('balance_due', '>', 0)
            ->when($company !== null, fn ($builder) => $builder->where('company_id', (int) $company));

        $marked = 0;

        $query->orderBy('id')->chunkById(200, function ($invoices) use (&$marked, $notifications): void {
            foreach ($invoices as $invoice) {
                $invoice->update(['status' => 'overdue']);

                WebhookService::dispatch('invoice.overdue', $invoice, [
                    'balance_due' => (float) $invoice->balance_due,
                    'due_date' => $invoice->due_date?->toDateString(),
                ]);

                $notifications->toCompany((int) $invoice->company_id, new InvoiceOverdueNotification($invoice));

                $marked++;
            }
        });

        $this->info(sprintf('Marked %d invoice(s) overdue.', $marked));

        return self::SUCCESS;
    }
}
