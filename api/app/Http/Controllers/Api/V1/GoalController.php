<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreGoalRequest;
use App\Http\Requests\Api\V1\UpdateGoalRequest;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class GoalController extends BaseController
{
    /** @var Goal */
    protected $model;

    protected string $resourceClass = GoalResource::class;

    protected string $storeRequestClass = StoreGoalRequest::class;

    protected string $updateRequestClass = UpdateGoalRequest::class;

    public function __construct()
    {
        $this->model = new Goal;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return GoalResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['type'] ??= Goal::TYPE_OBJECTIVE;
        $data['status'] ??= Goal::STATUS_DRAFT;
        $data['current_value'] ??= 0;
        $data['progress'] = 0;

        $goal = $this->model->create($data);

        return (new GoalResource($goal))->response()->setStatusCode(201);
    }

    /**
     * A goal is completed the moment its current value reaches the target.
     */
    public function progress(int $id): JsonResponse
    {
        $goal = $this->model->findOrFail($id);

        $data = request()->validate([
            'current_value' => 'required|numeric',
            'status' => ['nullable', Rule::in(Goal::STATUSES)],
        ]);

        $goal->current_value = $data['current_value'];

        $target = (float) ($goal->target_value ?? 0);
        $current = (float) $goal->current_value;

        $goal->progress = $target > 0
            ? max(0, min(100, (int) round($current / $target * 100)))
            : 0;

        if ($goal->progress >= 100) {
            $goal->status = Goal::STATUS_COMPLETED;
        }

        $goal->save();

        return (new GoalResource($goal->fresh()))->response();
    }

    public function cancel(int $id): JsonResponse
    {
        $goal = $this->model->findOrFail($id);

        if (in_array($goal->status, [Goal::STATUS_COMPLETED, Goal::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'Only a draft or active goal can be cancelled.',
            ], 422);
        }

        $goal->status = Goal::STATUS_CANCELLED;
        $goal->save();

        return (new GoalResource($goal->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'type'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
