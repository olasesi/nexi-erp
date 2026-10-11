<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDealActivityRequest;
use App\Http\Requests\Api\V1\UpdateDealActivityRequest;
use App\Http\Resources\DealActivityResource;
use App\Models\DealActivity;
use Illuminate\Http\JsonResponse;

class DealActivityController extends BaseController
{
    /** @var DealActivity */
    protected $model;

    protected string $resourceClass = DealActivityResource::class;

    protected string $storeRequestClass = StoreDealActivityRequest::class;

    protected string $updateRequestClass = UpdateDealActivityRequest::class;

    public function __construct()
    {
        $this->model = new DealActivity;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with(['deal']));

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return DealActivityResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['type'] = $data['type'] ?? DealActivity::TYPE_NOTE;
        $data['status'] = $data['status'] ?? DealActivity::STATUS_PENDING;

        $activity = $this->model->create($data);

        return (new DealActivityResource($activity->load('deal')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $activity = $this->model->with('deal')->findOrFail($id);

        return (new DealActivityResource($activity))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $activity = $this->model->findOrFail($id);
        $activity->update($request->validated());

        return (new DealActivityResource($activity->fresh('deal')))->response();
    }

    public function complete(int $id): JsonResponse
    {
        $activity = $this->model->findOrFail($id);

        if (! $activity->isPending()) {
            return response()->json([
                'message' => 'Only a pending activity can be completed.',
            ], 422);
        }

        $activity->update([
            'status' => DealActivity::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return (new DealActivityResource($activity->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'deal_id', 'type', 'status', 'user_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'description'];
    }
}
