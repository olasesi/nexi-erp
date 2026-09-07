<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AccountingService;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property Payment $model */
class PaymentController extends BaseController
{
    protected $model;

    protected string $resourceClass = PaymentResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StorePaymentRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdatePaymentRequest';

    public function __construct()
    {
        $this->model = new Payment;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['invoice', 'contact']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $data['payment_number'] = $this->generateNumber($data['type'] ?? 'receipt');
        $data['status'] = $data['status'] ?? 'pending';

        $payment = DB::transaction(function () use ($data) {
            $payment = $this->model->create($data);
            $this->reconcileAccounting($payment);

            return $payment;
        });

        return (new $this->resourceClass($payment->load('invoice')))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $payment = $this->model->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($payment, $data) {
            if (($data['status'] ?? $payment->status) !== 'pending') {
                $payment->update($data);
                $this->reconcileAccounting($payment);
            } else {
                $payment->update($data);
            }
        });

        return (new $this->resourceClass($payment->fresh('invoice')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $payment = $this->model->findOrFail($id);

        DB::transaction(function () use ($payment) {
            app(GeneralLedgerService::class)->voidForSource($payment);
            $payment->delete();
            $this->refreshInvoiceFromPayment($payment);
        });

        return response()->json(null, 204);
    }

    public function complete(int $id): JsonResponse
    {
        $payment = $this->model->findOrFail($id);

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'completed']);
            $this->reconcileAccounting($payment);
        });

        return (new $this->resourceClass($payment->fresh('invoice')))->response();
    }

    protected function reconcileAccounting(Payment $payment): void
    {
        app(AccountingService::class)->postPayment($payment);
        $this->refreshInvoiceFromPayment($payment);
    }

    protected function refreshInvoiceFromPayment(Payment $payment): void
    {
        if (! $payment->invoice_id) {
            return;
        }

        $invoice = Invoice::find($payment->invoice_id);

        if ($invoice) {
            app(AccountingService::class)->refreshInvoiceFromPayments($invoice);
        }
    }

    protected function generateNumber(string $type): string
    {
        $prefix = $type === 'payment' ? 'PAY' : 'REC';

        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) (Payment::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'type', 'status', 'invoice_id', 'contact_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['payment_number', 'reference'];
    }
}
