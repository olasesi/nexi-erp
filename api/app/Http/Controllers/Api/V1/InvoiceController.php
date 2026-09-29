<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\InvoiceResource;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\AccountingService;
use App\Services\GeneralLedgerService;
use App\Services\OrderService;
use App\Services\WebhookService;
use App\Support\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @property Invoice $model */
class InvoiceController extends BaseController
{
    protected $model;

    protected string $resourceClass = InvoiceResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreInvoiceRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateInvoiceRequest';

    public function __construct()
    {
        $this->model = new Invoice;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['items', 'contact']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $items = $data['items'] ?? [];
        unset($data['items']);

        $lines = app(OrderService::class)->buildLinesForInvoices($items);
        $totals = app(OrderService::class)->computeTotals($lines, (float) ($data['discount_rate'] ?? 0));

        $data['invoice_number'] = $this->generateNumber($data['type'] ?? 'invoice');
        $data['status'] = $data['status'] ?? 'draft';
        $data['paid_amount'] = 0;
        $data['balance_due'] = $totals['total'];

        $invoice = DB::transaction(function () use ($data, $lines, $totals) {
            $invoice = $this->model->create(array_merge($data, $totals));
            $invoice->items()->createMany($lines);

            return $invoice;
        });

        $invoice->load(['items', 'contact']);

        return (new $this->resourceClass($invoice))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $invoice = $this->model->findOrFail($id);
        $data = $request->validated();
        $oldStatus = $invoice->status;

        if ($invoice->status === 'paid' && isset($data['status']) && $data['status'] !== 'paid') {
            return response()->json(['message' => 'A fully paid invoice cannot change status.'], 422);
        }

        DB::transaction(function () use ($invoice, $data) {
            if (isset($data['items'])) {
                $lines = app(OrderService::class)->buildLinesForInvoices($data['items']);
                $invoice->update(array_merge($data, app(OrderService::class)->computeTotals($lines, (float) ($data['discount_rate'] ?? 0))));
                app(OrderService::class)->syncInvoiceItems($invoice, $data['items']);
            } else {
                $invoice->update($data);
            }
        });

        $this->syncAccounting($invoice->fresh(), $oldStatus);

        return (new $this->resourceClass($invoice->fresh(['items', 'contact'])))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $invoice = $this->model->findOrFail($id);

        if ($invoice->payments()->where('status', 'completed')->exists()) {
            return response()->json(['message' => 'Invoice with completed payments cannot be deleted.'], 422);
        }

        app(GeneralLedgerService::class)->voidForSource($invoice);
        $invoice->delete();

        return response()->json(null, 204);
    }

    public function pdf(int $id): Response
    {
        $invoice = $this->model->findOrFail($id)->load(['items', 'contact', 'company']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->stream($invoice->invoice_number.'.pdf');
    }

    public function email(int $id): JsonResponse
    {
        $invoice = $this->model->findOrFail($id)->load(['items', 'contact', 'company']);

        if (! $invoice->contact?->email) {
            return response()->json(['message' => 'The invoice contact has no email address.'], 422);
        }

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->output();

        Mail::to($invoice->contact->email)->send(new InvoiceMail($invoice, $pdf));

        WebhookService::dispatch('invoice.sent', $invoice, ['recipient' => $invoice->contact->email]);

        return response()->json(['message' => 'Invoice emailed to '.$invoice->contact->email.'.']);
    }

    /**
     * Download the invoice register as a CSV file, honouring the same
     * filters the JSON index uses.
     */
    public function export(): StreamedResponse
    {
        $query = $this->model->query()->with('contact');
        $query = $this->applyFilters($query);

        $rows = $query->orderBy('id')->cursor()->map(fn (Invoice $invoice) => [
            $invoice->invoice_number,
            $invoice->type,
            $invoice->status,
            $invoice->contact ? trim($invoice->contact->first_name.' '.$invoice->contact->last_name) : '',
            $invoice->issue_date->toDateString(),
            $invoice->due_date?->toDateString(),
            $invoice->currency,
            (float) $invoice->subtotal,
            (float) $invoice->tax_amount,
            (float) $invoice->discount_amount,
            (float) $invoice->total,
            (float) $invoice->paid_amount,
            (float) $invoice->balance_due,
        ])->all();

        return Csv::download(
            'invoices.csv',
            [
                'invoice_number', 'type', 'status', 'contact',
                'issue_date', 'due_date', 'currency', 'subtotal', 'tax_amount',
                'discount_amount', 'total', 'paid_amount', 'balance_due',
            ],
            $rows,
        );
    }

    protected function syncAccounting(Invoice $invoice, ?string $oldStatus): void
    {
        $posted = ['sent', 'partial', 'paid', 'overdue'];

        if ($invoice->status === 'cancelled') {
            app(GeneralLedgerService::class)->voidForSource($invoice);
        } elseif ($invoice->status !== 'draft' && in_array($invoice->status, $posted)) {
            app(AccountingService::class)->postInvoice($invoice);
        }

        if (in_array($oldStatus, $posted) && $invoice->status === 'draft') {
            app(GeneralLedgerService::class)->voidForSource($invoice);
        }
    }

    protected function generateNumber(string $type): string
    {
        $prefix = in_array($type, ['bill', 'debit_note']) ? 'BILL' : 'INV';

        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) (Invoice::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'type', 'status', 'contact_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['invoice_number'];
    }
}
