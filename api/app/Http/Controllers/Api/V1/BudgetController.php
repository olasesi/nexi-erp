<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreBudgetRequest;
use App\Http\Requests\Api\V1\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;

class BudgetController extends BaseController
{
    /** @var Budget */
    protected $model;

    protected string $resourceClass = BudgetResource::class;

    protected string $storeRequestClass = StoreBudgetRequest::class;

    protected string $updateRequestClass = UpdateBudgetRequest::class;

    public function __construct()
    {
        $this->model = new Budget;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('lines');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('start_date')->paginate(min($perPage, 100));

        return BudgetResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $budget = $this->model->with('lines')->findOrFail($id);

        return (new BudgetResource($budget))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Budget::STATUS_DRAFT;
        $data['total_planned'] = $data['total_planned'] ?? 0;
        $data['total_actual'] = $data['total_actual'] ?? 0;

        $budget = $this->model->create($data);

        return (new BudgetResource($budget->load('lines')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $budget = $this->model->findOrFail($id);

        $request = app($this->updateRequestClass);
        $budget->update($request->validated());

        return (new BudgetResource($budget->fresh('lines')))->response();
    }

    public function approve(int $id): JsonResponse
    {
        $budget = $this->model->findOrFail($id);

        if ($budget->status !== Budget::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft budget can be approved.',
            ], 422);
        }

        $budget->update(['status' => Budget::STATUS_ACTIVE]);

        return (new BudgetResource($budget->fresh('lines')))->response();
    }

    public function close(int $id): JsonResponse
    {
        $budget = $this->model->findOrFail($id);

        if ($budget->status !== Budget::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'Only an active budget can be closed.',
            ], 422);
        }

        $budget->update(['status' => Budget::STATUS_CLOSED]);

        return (new BudgetResource($budget->fresh('lines')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'period_type'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
