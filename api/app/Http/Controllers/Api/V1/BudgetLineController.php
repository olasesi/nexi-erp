<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreBudgetLineRequest;
use App\Http\Requests\Api\V1\UpdateBudgetLineRequest;
use App\Http\Resources\BudgetLineResource;
use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Http\JsonResponse;

class BudgetLineController extends BaseController
{
    /** @var BudgetLine */
    protected $model;

    protected string $resourceClass = BudgetLineResource::class;

    protected string $storeRequestClass = StoreBudgetLineRequest::class;

    protected string $updateRequestClass = UpdateBudgetLineRequest::class;

    public function __construct()
    {
        $this->model = new BudgetLine;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('created_at')->paginate(min($perPage, 100));

        return BudgetLineResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $line = $this->model->findOrFail($id);

        return (new BudgetLineResource($line))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $budget = Budget::findOrFail((int) $data['budget_id']);

        $data['company_id'] = $data['company_id'] ?? $budget->company_id;
        $data['type'] = $data['type'] ?? BudgetLine::TYPE_EXPENSE;

        $line = $this->model->create($data);

        $budget->recomputeTotals()->save();

        return (new BudgetLineResource($line))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $line = $this->model->findOrFail($id);
        $originalBudgetId = (int) $line->budget_id;

        $request = app($this->updateRequestClass);
        $line->update($request->validated());

        if ($budget = $line->budget) {
            $budget->recomputeTotals()->save();
        }

        if ((int) $line->budget_id !== $originalBudgetId) {
            if ($original = Budget::find($originalBudgetId)) {
                $original->recomputeTotals()->save();
            }
        }

        return (new BudgetLineResource($line->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $line = $this->model->findOrFail($id);
        $budget = $line->budget;

        $line->delete();

        $budget?->recomputeTotals()->save();

        return response()->json(null, 204);
    }

    public function restore(int $id): JsonResponse
    {
        $response = parent::restore($id);

        $line = BudgetLine::withTrashed()->findOrFail($id);

        if ($budget = Budget::find($line->budget_id)) {
            $budget->recomputeTotals()->save();
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'budget_id', 'type'];
    }
}
