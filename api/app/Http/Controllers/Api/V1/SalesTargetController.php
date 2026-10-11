<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSalesTargetRequest;
use App\Http\Requests\Api\V1\UpdateSalesTargetRequest;
use App\Http\Resources\SalesTargetResource;
use App\Models\SalesOrder;
use App\Models\SalesTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class SalesTargetController extends BaseController
{
    /** @var SalesTarget */
    protected $model;

    protected string $resourceClass = SalesTargetResource::class;

    protected string $storeRequestClass = StoreSalesTargetRequest::class;

    protected string $updateRequestClass = UpdateSalesTargetRequest::class;

    public function __construct()
    {
        $this->model = new SalesTarget;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('employee'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return SalesTargetResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['period_type'] = $data['period_type'] ?? SalesTarget::PERIOD_MONTHLY;
        $data['status'] = $data['status'] ?? SalesTarget::STATUS_ACTIVE;

        $target = $this->model->create($data);

        return (new SalesTargetResource($target->load('employee')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Recompute the achieved amount from the company's sales orders for the
     * target period, unless the caller passes an explicit amount, then mark
     * the target achieved once the target is met.
     */
    public function progress(int $id): JsonResponse
    {
        $target = $this->model->findOrFail($id);

        $input = request()->input('achieved_amount');

        $achieved = is_numeric($input)
            ? (float) $input
            : $this->sumSalesOrders($target);

        $target->achieved_amount = $achieved;
        $target->status = $achieved >= (float) $target->target_amount
            ? SalesTarget::STATUS_ACHIEVED
            : SalesTarget::STATUS_ACTIVE;

        $target->save();

        return (new SalesTargetResource($target->fresh()))->response();
    }

    /**
     * Sum the period's sales orders. The money and sales-rep column names are
     * probed from the sales_orders table so the add-on still works if the
     * order schema is customised; falling back to `total` keeps the sum valid
     * even when no recognised column exists.
     */
    private function sumSalesOrders(SalesTarget $target): float
    {
        $table = (new SalesOrder)->getTable();

        $moneyColumn = collect(['total', 'subtotal', 'paid_amount', 'balance_due'])
            ->first(fn (string $column): bool => Schema::hasColumn($table, $column)) ?? 'total';

        $salesRepColumn = collect(['created_by', 'sales_rep_id', 'user_id', 'salesperson_id'])
            ->first(fn (string $column): bool => Schema::hasColumn($table, $column));

        $query = SalesOrder::query()->where($table.'.company_id', $target->company_id);

        $start = $target->period_start?->startOfDay();
        $end = $target->period_end?->endOfDay();

        if ($start !== null && $end !== null) {
            $query->whereBetween($table.'.created_at', [$start, $end]);
        }

        $employee = $target->employee;

        if ($salesRepColumn !== null && $employee !== null && $employee->user_id !== null) {
            $query->where($table.'.'.$salesRepColumn, $employee->user_id);
        }

        return (float) $query->sum($table.'.'.$moneyColumn);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'employee_id', 'period_type'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
