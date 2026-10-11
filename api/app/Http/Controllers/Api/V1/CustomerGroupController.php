<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCustomerGroupRequest;
use App\Http\Requests\Api\V1\UpdateCustomerGroupRequest;
use App\Http\Resources\CustomerGroupResource;
use App\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;

class CustomerGroupController extends BaseController
{
    /** @var CustomerGroup */
    protected $model;

    protected string $resourceClass = CustomerGroupResource::class;

    protected string $storeRequestClass = StoreCustomerGroupRequest::class;

    protected string $updateRequestClass = UpdateCustomerGroupRequest::class;

    public function __construct()
    {
        $this->model = new CustomerGroup;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return CustomerGroupResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['discount_rate'] = $data['discount_rate'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        $group = $this->model->create($data);

        return (new CustomerGroupResource($group))->response()->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
