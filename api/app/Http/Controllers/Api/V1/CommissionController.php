<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCommissionRequest;
use App\Http\Requests\Api\V1\UpdateCommissionRequest;
use App\Http\Resources\CommissionResource;
use App\Models\Commission;
use Illuminate\Http\JsonResponse;

class CommissionController extends BaseController
{
    /** @var Commission */
    protected $model;

    protected string $resourceClass = CommissionResource::class;

    protected string $storeRequestClass = StoreCommissionRequest::class;

    protected string $updateRequestClass = UpdateCommissionRequest::class;

    public function __construct()
    {
        $this->model = new Commission;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('employee'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return CommissionResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Commission::STATUS_PENDING;

        if (! isset($data['amount'])) {
            $data['amount'] = round(((float) ($data['sale_amount'] ?? 0)) * ((float) ($data['rate'] ?? 0)) / 100, 2);
        }

        $item = $this->model->create($data);

        return (new CommissionResource($item))->response()->setStatusCode(201);
    }

    /**
     * Sign off a pending commission; only commissions that have not been
     * approved yet may take this step.
     */
    public function approve(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if ($item->status !== Commission::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only a pending commission can be approved.',
            ], 422);
        }

        $item->update([
            'status' => Commission::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        return (new CommissionResource($item->fresh()))->response();
    }

    /**
     * Settle an approved commission; only approved commissions may be paid.
     */
    public function markPaid(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if ($item->status !== Commission::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Only an approved commission can be marked paid.',
            ], 422);
        }

        $item->update([
            'status' => Commission::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return (new CommissionResource($item->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'employee_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'reference'];
    }
}
