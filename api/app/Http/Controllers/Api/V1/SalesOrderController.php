<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;

/** @property SalesOrder $model */
class SalesOrderController extends BaseController
{
    protected $model;

    protected string $resourceClass = SalesOrderResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreSalesOrderRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateSalesOrderRequest';

    public function __construct()
    {
        $this->model = new SalesOrder;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['items', 'contact']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $data['order_number'] = 'SO-'.now()->format('Ymd').'-'.str_pad((string) (SalesOrder::max('id') + 1), 5, '0', STR_PAD_LEFT);

        $item = $this->model->create($data);

        $item->load(['items', 'contact']);

        return (new $this->resourceClass($item))->response()->setStatusCode(201);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'payment_status', 'contact_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['order_number'];
    }
}
