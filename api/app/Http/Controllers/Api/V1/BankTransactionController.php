<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ImportBankTransactionsRequest;
use App\Http\Resources\BankTransactionResource;
use App\Models\BankTransaction;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property BankTransaction $model */
class BankTransactionController extends BaseController
{
    protected $model;

    protected string $resourceClass = BankTransactionResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreBankTransactionRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\StoreBankTransactionRequest';

    public function __construct()
    {
        $this->model = new BankTransaction;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('bankAccount');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function import(): JsonResponse
    {
        $request = app(ImportBankTransactionsRequest::class);
        $data = $request->validated();

        $created = DB::transaction(function () use ($data) {
            return collect($data['transactions'])->map(fn ($txn) => $this->model->query()->create(
                array_merge($txn, [
                    'company_id' => $data['company_id'],
                    'bank_account_id' => $data['bank_account_id'],
                    'status' => 'cleared',
                    'reconciliation_status' => 'unreconciled',
                ])
            ));
        });

        return $this->resourceClass::collection($created)->response()->setStatusCode(201);
    }

    /**
     * Match a bank transaction to a payment, closing the loop between
     * bank statements and the general ledger.
     */
    public function matchTransaction(int $id): JsonResponse
    {
        $transaction = $this->model->findOrFail($id);
        $paymentId = (int) request()->integer('payment_id');

        $payment = Payment::where('id', $paymentId)->where('status', 'completed')->first();

        if (! $payment) {
            return response()->json(['message' => 'A completed payment is required to match.'], 422);
        }

        // Auto-reconcile when the amounts agree (allow small rounding tolerance).
        $matches = abs((float) $transaction->amount) - (float) $payment->amount;

        $transaction->update([
            'matched_payment_id' => $payment->id,
            'reconciliation_status' => abs($matches) < 0.005 ? 'matched' : 'unreconciled',
        ]);

        return (new $this->resourceClass($transaction->fresh()))->response();
    }

    public function unmatch(int $id): JsonResponse
    {
        $transaction = $this->model->findOrFail($id);

        $transaction->update([
            'matched_payment_id' => null,
            'reconciliation_status' => 'unreconciled',
        ]);

        return (new $this->resourceClass($transaction->fresh()))->response();
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'bank_account_id', 'reconciliation_status', 'status', 'category'];
    }

    protected function getSearchableFields(): array
    {
        return ['description', 'reference'];
    }
}
