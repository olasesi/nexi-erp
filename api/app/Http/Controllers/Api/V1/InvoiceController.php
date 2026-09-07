<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\AccountingService;
use App\Services\GeneralLedgerService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        $totals = app(OrderService::class)->computeTotals($lines);

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
                $invoice->update(array_merge($data, app(OrderService::class)->computeTotals($lines)));
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
