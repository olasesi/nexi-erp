<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreLaundryItemRequest;
use App\Http\Requests\Api\V1\UpdateLaundryItemRequest;
use App\Http\Resources\LaundryItemResource;
use App\Models\LaundryItem;
use App\Models\LaundryOrder;
use App\Services\LaundryOrderService;
use Illuminate\Http\JsonResponse;

class LaundryItemController extends BaseController
{
    /** @var LaundryItem */
    protected $model;

    protected string $resourceClass = LaundryItemResource::class;

    protected string $storeRequestClass = StoreLaundryItemRequest::class;

    protected string $updateRequestClass = UpdateLaundryItemRequest::class;

    public function __construct()
    {
        $this->model = new LaundryItem;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('product');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('id')->paginate(min($perPage, 100));

        return LaundryItemResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $order = LaundryOrder::findOrFail((int) $data['laundry_order_id']);
        $this->assertOrderEditable($order);

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['service'] = $data['service'] ?? LaundryItem::SERVICE_WASH;
        $data['subtotal'] = $this->lineTotal((int) $data['quantity'], (float) ($data['unit_price'] ?? 0));

        $item = $this->model->create($data);

        app(LaundryOrderService::class)->recalculate($order);

        return (new LaundryItemResource($item))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);
        $order = LaundryOrder::findOrFail($item->laundry_order_id);
        $this->assertOrderEditable($order);

        $request = app($this->updateRequestClass);
        $data = $request->validated();

        $quantity = (int) ($data['quantity'] ?? $item->quantity);
        $unitPrice = (float) ($data['unit_price'] ?? $item->unit_price);
        $data['subtotal'] = $this->lineTotal($quantity, $unitPrice);

        $item->update($data);

        app(LaundryOrderService::class)->recalculate($order);

        return (new LaundryItemResource($item->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);
        $order = LaundryOrder::findOrFail($item->laundry_order_id);
        $this->assertOrderEditable($order);

        $item->delete();

        app(LaundryOrderService::class)->recalculate($order);

        return response()->json(null, 204);
    }

    public function restore(int $id): JsonResponse
    {
        $response = parent::restore($id);

        $item = LaundryItem::withTrashed()->findOrFail($id);

        if ($order = LaundryOrder::find($item->laundry_order_id)) {
            app(LaundryOrderService::class)->recalculate($order);
        }

        return $response;
    }

    private function lineTotal(int $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }

    private function assertOrderEditable(LaundryOrder $order): void
    {
        if (in_array($order->status, [LaundryOrder::STATUS_DELIVERED, LaundryOrder::STATUS_CANCELLED], true)) {
            abort(response()->json([
                'message' => 'The items of a delivered or cancelled order cannot be changed.',
            ], 422));
        }
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'laundry_order_id', 'service', 'product_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['description'];
    }
}
