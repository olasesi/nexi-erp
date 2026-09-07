<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ReconciliationResource;
use App\Models\Reconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property Reconciliation $model */
class ReconciliationController extends BaseController
{
    protected $model;

    protected string $resourceClass = ReconciliationResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreReconciliationRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\StoreReconciliationRequest';

    public function __construct()
    {
        $this->model = new Reconciliation;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('bankAccount');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $transactionIds = $data['transaction_ids'] ?? [];
        unset($data['transaction_ids']);

        $data['status'] = 'open';

        $reconciliation = DB::transaction(function () use ($data, $transactionIds) {
            $reconciliation = $this->model->create($data);

            foreach ($transactionIds as $id) {
                $reconciliation->items()->create([
                    'bank_transaction_id' => $id,
                    'status' => 'matched',
                ]);
            }

            return $reconciliation;
        });

        return (new $this->resourceClass($reconciliation->load('bankAccount')))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $reconciliation = $this->model
            ->with(['bankAccount', 'items.bankTransaction'])
            ->findOrFail($id);

        return (new $this->resourceClass($reconciliation))->response();
    }

    public function complete(int $id): JsonResponse
    {
        $reconciliation = $this->model->with('items')->findOrFail($id);

        if ($reconciliation->status === 'completed') {
            return response()->json(['message' => 'Reconciliation is already completed.'], 422);
        }

        DB::transaction(function () use ($reconciliation) {
            foreach ($reconciliation->items as $item) {
                $item->bankTransaction()->update(['reconciliation_status' => 'reconciled']);
            }

            $reconciliation->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        return (new $this->resourceClass($reconciliation->fresh(['bankAccount', 'items.bankTransaction'])))->response();
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'bank_account_id', 'status'];
    }
}
