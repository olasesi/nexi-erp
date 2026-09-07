<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\IncomeResource;
use App\Models\Income;
use Illuminate\Http\JsonResponse;

/** @property Income $model */
class IncomeController extends BaseController
{
    protected $model;

    protected string $resourceClass = IncomeResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreIncomeRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateIncomeRequest';

    public function __construct()
    {
        $this->model = new Income;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('incomeCategory');
        $query = $this->applyFilters($query);

        if ($from = request('from_date')) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = request('to_date')) {
            $query->whereDate('date', '<=', $to);
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);

        $data = $request->validated();
        $data['reference_no'] ??= $this->nextRef('INC', Income::class);
        $data['created_by'] = $data['created_by'] ?? auth('api')->user()?->id;

        $income = $this->model->create($data);

        return (new $this->resourceClass($income->load('incomeCategory')))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $income = $this->model->with('incomeCategory')->findOrFail($id);

        return (new $this->resourceClass($income))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $income = $this->model->findOrFail($id);
        $income->update($request->validated());

        return (new $this->resourceClass($income->fresh('incomeCategory')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $income = $this->model->findOrFail($id);
        $income->delete();

        return response()->json(null, 204);
    }

    private function nextRef(string $prefix, $modelClass, string $column = 'reference_no'): string
    {
        $yymm = date('ym');

        $last = (clone $modelClass::query())
            ->where($column, 'like', "{$prefix}-{$yymm}-%")
            ->count();

        return sprintf('%s-%s-%04d', $prefix, $yymm, $last + 1);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'income_category_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['reference_no', 'notes'];
    }
}
