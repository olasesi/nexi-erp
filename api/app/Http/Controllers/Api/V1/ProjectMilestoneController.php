<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProjectMilestoneRequest;
use App\Http\Requests\Api\V1\UpdateProjectMilestoneRequest;
use App\Http\Resources\ProjectMilestoneResource;
use App\Models\ProjectMilestone;
use Illuminate\Http\JsonResponse;

class ProjectMilestoneController extends BaseController
{
    /** @var ProjectMilestone */
    protected $model;

    protected string $resourceClass = ProjectMilestoneResource::class;

    protected string $storeRequestClass = StoreProjectMilestoneRequest::class;

    protected string $updateRequestClass = UpdateProjectMilestoneRequest::class;

    /** Relations carried on every single-milestone payload. */
    /** @var list<string> */
    protected array $includes = ['project'];

    public function __construct()
    {
        $this->model = new ProjectMilestone;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with($this->includes);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('due_date')->orderByDesc('id')->paginate(min($perPage, 100));

        return ProjectMilestoneResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $data['status'] = $data['status'] ?? ProjectMilestone::STATUS_PENDING;

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        // Stamp the reached date when a milestone is recorded already met.
        if ($data['status'] === ProjectMilestone::STATUS_REACHED) {
            $data['reached_at'] = $data['reached_at'] ?? now();
        }

        $milestone = $this->model->create($data);

        return (new ProjectMilestoneResource($milestone->load($this->includes)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $milestone = $this->model->findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            // Keep reached_at in step with the status the same way tasks keep
            // completed_at, so upcoming/overdue counts stay trustworthy.
            if ($data['status'] === ProjectMilestone::STATUS_REACHED) {
                $data['reached_at'] = $data['reached_at'] ?? ($milestone->reached_at ?? now());
            } else {
                $data['reached_at'] = null;
            }
        }

        $milestone->update($data);

        return (new ProjectMilestoneResource($milestone->fresh($this->includes)))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'project_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'description'];
    }
}
