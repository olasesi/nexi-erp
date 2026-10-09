<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreLaundryOrderRequest;
use App\Http\Requests\Api\V1\UpdateLaundryOrderRequest;
use App\Http\Resources\LaundryOrderResource;
use App\Models\LaundryOrder;
use App\Services\LaundryOrderService;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;

class LaundryOrderController extends BaseController
{
    /** @var LaundryOrder */
    protected $model;

    protected string $resourceClass = LaundryOrderResource::class;

    protected string $storeRequestClass = StoreLaundryOrderRequest::class;

    protected string $updateRequestClass = UpdateLaundryOrderRequest::class;

    private LaundryOrderService $service;

    public function __construct()
    {
        $this->model = new LaundryOrder;
        $this->service = app(LaundryOrderService::class);
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['contact', 'items']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return LaundryOrderResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $order = $this->model->with(['contact', 'items'])->findOrFail($id);

        return (new LaundryOrderResource($order))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $companyId = (int) ($data['company_id'] ?? $user->company_id);
            $data['company_id'] = $companyId;
            $data['created_by'] = $user->id;
        } else {
            $companyId = (int) $data['company_id'];
        }

        $data['order_number'] = $this->service->generateNumber($companyId);
        $data['status'] = $data['status'] ?? LaundryOrder::STATUS_RECEIVED;

        $order = $this->model->create($data);

        app(MetricsService::class)->recordVertical('laundry', (string) $order->status);

        return (new LaundryOrderResource($order->load(['contact', 'items'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $order = $this->model->findOrFail($id);

        if (in_array($order->status, [LaundryOrder::STATUS_DELIVERED, LaundryOrder::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'A delivered or cancelled order cannot be edited.',
            ], 422);
        }

        $request = app($this->updateRequestClass);
        $order->update($request->validated());

        $order = $this->service->recalculate($order);

        return (new LaundryOrderResource($order->load(['contact', 'items'])))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $order = $this->model->findOrFail($id);

        if ($order->status === LaundryOrder::STATUS_DELIVERED) {
            return response()->json([
                'message' => 'A delivered order is kept for the record and cannot be deleted.',
            ], 422);
        }

        $order->delete();

        return response()->json(null, 204);
    }

    public function advance(int $id): JsonResponse
    {
        $order = $this->model->findOrFail($id);
        $order = $this->service->advance($order);

        return (new LaundryOrderResource($order->load(['contact', 'items'])))->response();
    }

    public function cancel(int $id): JsonResponse
    {
        $order = $this->model->findOrFail($id);
        $order = $this->service->cancel($order);

        return (new LaundryOrderResource($order->load(['contact', 'items'])))->response();
    }

    /**
     * Pipeline board: how many orders sit in each stage and what they are worth.
     */
    public function summary(): JsonResponse
    {
        $companyId = (int) auth('api')->user()?->company_id;

        $rows = LaundryOrder::query()
            ->selectRaw('status, count(*) as total')
            ->where('company_id', $companyId)
            ->groupBy('status')
            ->get();

        $byStatus = array_fill_keys(LaundryOrder::STATUSES, 0);

        foreach ($rows as $row) {
            $byStatus[(string) $row->getAttribute('status')] = (int) $row->getAttribute('total');
        }

        $openValue = (float) LaundryOrder::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', [LaundryOrder::STATUS_DELIVERED, LaundryOrder::STATUS_CANCELLED])
            ->sum('total');

        $deliveredValue = (float) LaundryOrder::query()
            ->where('company_id', $companyId)
            ->where('status', LaundryOrder::STATUS_DELIVERED)
            ->sum('total');

        return response()->json([
            'summary' => [
                'total_orders' => array_sum($byStatus),
                'by_status' => $byStatus,
                'open_value' => round($openValue, 2),
                'delivered_value' => round($deliveredValue, 2),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'contact_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['order_number', 'notes'];
    }
}
