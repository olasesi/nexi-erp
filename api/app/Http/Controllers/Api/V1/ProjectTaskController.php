<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProjectTaskRequest;
use App\Http\Requests\Api\V1\UpdateProjectTaskRequest;
use App\Http\Resources\ProjectTaskResource;
use App\Models\ProjectTask;
use Illuminate\Http\JsonResponse;

class ProjectTaskController extends BaseController
{
    /** @var ProjectTask */
    protected $model;

    protected string $resourceClass = ProjectTaskResource::class;

    protected string $storeRequestClass = StoreProjectTaskRequest::class;

    protected string $updateRequestClass = UpdateProjectTaskRequest::class;

    /** Relations carried on every single-task payload. */
    /** @var list<string> */
    protected array $includes = ['project', 'assignedTo'];

    public function __construct()
    {
        $this->model = new ProjectTask;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with($this->includes);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('due_date')->orderByDesc('id')->paginate(min($perPage, 100));

        return ProjectTaskResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $data['status'] = $data['status'] ?? ProjectTask::STATUS_TODO;
        $data['priority'] = $data['priority'] ?? ProjectTask::PRIORITY_MEDIUM;

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        // Stamp completion when a task is raised already finished.
        if ($data['status'] === ProjectTask::STATUS_DONE) {
            $data['completed_at'] = $data['completed_at'] ?? now();
        }

        $task = $this->model->create($data);

        return (new ProjectTaskResource($task->load($this->includes)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $task = $this->model->findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            // Keep completed_at in step with the status so the dashboard can
            // trust it without recomputing completion from the status column.
            if ($data['status'] === ProjectTask::STATUS_DONE) {
                $data['completed_at'] = $data['completed_at'] ?? ($task->completed_at ?? now());
            } else {
                $data['completed_at'] = null;
            }
        }

        $task->update($data);

        return (new ProjectTaskResource($task->fresh($this->includes)))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'project_id', 'status', 'priority', 'assigned_to'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'description'];
    }
}
