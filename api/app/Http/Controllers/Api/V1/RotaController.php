<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreRotaRequest;
use App\Http\Requests\Api\V1\UpdateRotaRequest;
use App\Http\Resources\RotaResource;
use App\Models\Rota;
use Illuminate\Http\JsonResponse;

class RotaController extends BaseController
{
    /** @var Rota */
    protected $model;

    protected string $resourceClass = RotaResource::class;

    protected string $storeRequestClass = StoreRotaRequest::class;

    protected string $updateRequestClass = UpdateRotaRequest::class;

    public function __construct()
    {
        $this->model = new Rota;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->withCount('entries');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return RotaResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Rota::STATUS_DRAFT;

        $rota = $this->model->create($data);

        return (new RotaResource($rota))->response()->setStatusCode(201);
    }

    public function publish(int $id): JsonResponse
    {
        $rota = $this->model->findOrFail($id);

        if ($rota->status !== Rota::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft rota can be published.',
            ], 422);
        }

        $rota->update(['status' => Rota::STATUS_PUBLISHED]);

        return (new RotaResource($rota->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
