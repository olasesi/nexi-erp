<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSalesReturnRequest;
use App\Http\Requests\Api\V1\UpdateSalesReturnRequest;
use App\Http\Resources\SalesReturnResource;
use App\Models\SalesReturn;
use Illuminate\Http\JsonResponse;

class SalesReturnController extends BaseController
{
    /** @var SalesReturn */
    protected $model;

    protected string $resourceClass = SalesReturnResource::class;

    protected string $storeRequestClass = StoreSalesReturnRequest::class;

    protected string $updateRequestClass = UpdateSalesReturnRequest::class;

    public function __construct()
    {
        $this->model = new SalesReturn;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->withCount('items');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return SalesReturnResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? SalesReturn::STATUS_DRAFT;

        $return = $this->model->create($data);
        $return->recalculateTotals();

        return (new SalesReturnResource($return))->response()->setStatusCode(201);
    }

    public function complete(int $id): JsonResponse
    {
        $return = $this->model->findOrFail($id);

        if ($return->status !== SalesReturn::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft return can be completed.',
            ], 422);
        }

        $return->update(['status' => SalesReturn::STATUS_COMPLETED]);

        return (new SalesReturnResource($return->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'contact_id', 'invoice_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['return_number', 'reason'];
    }
}
