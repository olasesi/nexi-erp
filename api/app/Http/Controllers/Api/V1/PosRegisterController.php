<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StorePosRegisterRequest;
use App\Http\Requests\Api\V1\UpdatePosRegisterRequest;
use App\Http\Resources\PosRegisterResource;
use App\Models\PosRegister;
use Illuminate\Http\JsonResponse;

/** @property PosRegister $model */
class PosRegisterController extends BaseController
{
    protected $model;

    protected string $resourceClass = PosRegisterResource::class;

    protected string $storeRequestClass = StorePosRegisterRequest::class;

    protected string $updateRequestClass = UpdatePosRegisterRequest::class;

    public function __construct()
    {
        $this->model = new PosRegister;
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $item = $this->model->create($data);

        return (new $this->resourceClass($item->fresh()))->response()->setStatusCode(201);
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()
            ->with('warehouse')
            ->withCount('orders');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->latest('id')->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'warehouse_id', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'code'];
    }
}
