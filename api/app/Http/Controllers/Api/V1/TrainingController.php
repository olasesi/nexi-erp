<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTrainingRequest;
use App\Http\Requests\Api\V1\UpdateTrainingRequest;
use App\Http\Resources\TrainingResource;
use App\Models\Training;
use Illuminate\Http\JsonResponse;

class TrainingController extends BaseController
{
    /** @var Training */
    protected $model;

    protected string $resourceClass = TrainingResource::class;

    protected string $storeRequestClass = StoreTrainingRequest::class;

    protected string $updateRequestClass = UpdateTrainingRequest::class;

    public function __construct()
    {
        $this->model = new Training;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('type');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('start_date')->paginate(min($perPage, 100));

        return TrainingResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Training::STATUS_SCHEDULED;

        $training = $this->model->create($data);

        return (new TrainingResource($training->load('type')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $training = $this->model->with('type')->findOrFail($id);

        return (new TrainingResource($training))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $training = $this->model->findOrFail($id);
        $training->update($request->validated());

        return (new TrainingResource($training->fresh('type')))->response();
    }

    public function cancel(int $id): JsonResponse
    {
        $training = $this->model->findOrFail($id);

        if (in_array($training->status, [Training::STATUS_CANCELLED, Training::STATUS_COMPLETED], true)) {
            return response()->json([
                'message' => 'A cancelled or completed training cannot be cancelled.',
            ], 422);
        }

        $training->update(['status' => Training::STATUS_CANCELLED]);

        return (new TrainingResource($training->fresh('type')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'training_type_id', 'branch'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'trainer'];
    }
}
