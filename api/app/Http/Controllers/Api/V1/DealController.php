<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DealResource;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;

/** @property Deal $model */
class DealController extends BaseController
{
    protected $model;

    protected string $resourceClass = DealResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreDealRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateDealRequest';

    public function __construct()
    {
        $this->model = new Deal;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['stage', 'lead', 'contact', 'assignedTo']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $data['status'] = $data['status'] ?? Deal::STATUS_OPEN;

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $deal = $this->model->create($data);
        $deal->load(['stage', 'lead', 'contact', 'assignedTo']);

        return (new $this->resourceClass($deal))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $deal = $this->model->with(['stage', 'lead', 'contact', 'assignedTo'])->findOrFail($id);

        return (new $this->resourceClass($deal))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $deal = $this->model->findOrFail($id);
        $deal->update($request->validated());

        return (new $this->resourceClass($deal->fresh(['stage', 'lead', 'contact', 'assignedTo'])))->response();
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'crm_stage_id', 'lead_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
