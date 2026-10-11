<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSalesAgentOrderRequest;
use App\Http\Requests\Api\V1\UpdateSalesAgentOrderRequest;
use App\Http\Requests\Api\V1\UpdateSalesAgentOrderStatusRequest;
use App\Http\Resources\SalesAgentOrderResource;
use App\Models\SalesAgent;
use App\Models\SalesAgentOrder;
use Illuminate\Http\JsonResponse;

class SalesAgentOrderController extends BaseController
{
    /** @var SalesAgentOrder */
    protected $model;

    protected string $resourceClass = SalesAgentOrderResource::class;

    protected string $storeRequestClass = StoreSalesAgentOrderRequest::class;

    protected string $updateRequestClass = UpdateSalesAgentOrderRequest::class;

    protected string $updateStatusRequestClass = UpdateSalesAgentOrderStatusRequest::class;

    public function __construct()
    {
        $this->model = new SalesAgentOrder;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('salesAgent'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return SalesAgentOrderResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['status'] = $data['status'] ?? SalesAgentOrder::STATUS_PENDING;

        if (! isset($data['commission_amount'])) {
            $agent = SalesAgent::query()->whereKey($data['sales_agent_id'])->firstOrFail();
            $rate = (float) $agent->commission_rate;
            $data['commission_amount'] = round(((float) ($data['amount'] ?? 0)) * $rate / 100, 2);
        }

        $order = $this->model->create($data);

        return (new SalesAgentOrderResource($order->load('salesAgent')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Advance an order through its status pipeline.
     */
    public function updateStatus(int $id): JsonResponse
    {
        $request = app($this->updateStatusRequestClass);
        $order = $this->model->findOrFail($id);
        $order->update($request->validated());

        return (new SalesAgentOrderResource($order->fresh('salesAgent')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'sales_agent_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['order_number', 'customer_name'];
    }
}
