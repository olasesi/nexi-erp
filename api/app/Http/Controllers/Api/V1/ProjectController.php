<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends BaseController
{
    /** @var Project */
    protected $model;

    protected string $resourceClass = ProjectResource::class;

    protected string $storeRequestClass = StoreProjectRequest::class;

    protected string $updateRequestClass = UpdateProjectRequest::class;

    /** Relations carried on every single-project payload. */
    /** @var list<string> */
    protected array $includes = ['owner', 'createdBy'];

    public function __construct(private readonly ProjectAnalytics $analytics)
    {
        $this->model = new Project;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()
            ->with($this->includes)
            ->withCount(['tasks', 'milestones']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->latest('id')->paginate(min($perPage, 100));

        return ProjectResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $data['status'] = $data['status'] ?? ProjectStatus::Planning->value;

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $project = $this->model->create($data);

        return (new ProjectResource($project->load($this->includes)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $project = $this->model->with($this->includes)
            ->withCount(['tasks', 'milestones'])
            ->findOrFail($id);

        return (new ProjectResource($project))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $project = $this->model->findOrFail($id);
        $project->update($request->validated());

        return (new ProjectResource($project->fresh($this->includes)))->response();
    }

    /**
     * Portfolio tile: the same aggregates the dashboard card reads, served
     * directly off the project module so the SPA can render a projects home
     * without going through the dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $companyId = $data['company_id'] ?? auth('api')->user()?->company_id;

        if ($companyId === null) {
            return response()->json([
                'message' => 'A company_id is required for the project summary.',
            ], 422);
        }

        $companyId = (int) $companyId;

        return response()->json(array_merge([
            'company_id' => $companyId,
            'as_of' => now()->toDateString(),
        ], $this->analytics->summary($companyId)));
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'owner_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'description'];
    }
}
