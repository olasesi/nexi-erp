<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;

/** @property Expense $model */
class ExpenseController extends BaseController
{
    protected $model;

    protected string $resourceClass = ExpenseResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreExpenseRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateExpenseRequest';

    public function __construct()
    {
        $this->model = new Expense;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('expenseCategory');
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
        $data['reference_no'] ??= $this->nextRef('EXP', Expense::class);
        $data['created_by'] = $data['created_by'] ?? auth('api')->user()?->id;

        $expense = $this->model->create($data);

        return (new $this->resourceClass($expense->load('expenseCategory')))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $expense = $this->model->with('expenseCategory')->findOrFail($id);

        return (new $this->resourceClass($expense))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $expense = $this->model->findOrFail($id);
        $expense->update($request->validated());

        return (new $this->resourceClass($expense->fresh('expenseCategory')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $expense = $this->model->findOrFail($id);
        $expense->delete();

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
        return ['company_id', 'expense_category_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['reference_no', 'notes'];
    }
}
