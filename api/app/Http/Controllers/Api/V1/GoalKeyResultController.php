<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreGoalKeyResultRequest;
use App\Http\Requests\Api\V1\UpdateGoalKeyResultRequest;
use App\Http\Resources\GoalKeyResultResource;
use App\Models\GoalKeyResult;
use Illuminate\Http\JsonResponse;

class GoalKeyResultController extends BaseController
{
    /** @var GoalKeyResult */
    protected $model;

    protected string $resourceClass = GoalKeyResultResource::class;

    protected string $storeRequestClass = StoreGoalKeyResultRequest::class;

    protected string $updateRequestClass = UpdateGoalKeyResultRequest::class;

    public function __construct()
    {
        $this->model = new GoalKeyResult;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('created_at')->paginate(min($perPage, 100));

        return GoalKeyResultResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['current_value'] ??= 0;
        $data['status'] ??= GoalKeyResult::STATUS_ACTIVE;
        $data['progress'] = $this->computeProgress(
            (float) $data['current_value'],
            (float) ($data['target_value'] ?? 0),
        );

        $item = $this->model->create($data);

        return (new GoalKeyResultResource($item))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $data = $request->validated();

        $data['progress'] = $this->computeProgress(
            (float) ($data['current_value'] ?? $item->current_value),
            (float) ($data['target_value'] ?? $item->target_value),
        );

        $item->update($data);

        return (new GoalKeyResultResource($item->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'goal_id'];
    }

    private function computeProgress(float $current, float $target): int
    {
        if ($target <= 0) {
            return 0;
        }

        return min(100, (int) round($current / $target * 100));
    }
}
