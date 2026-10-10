<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreJobPostingRequest;
use App\Http\Requests\Api\V1\UpdateJobPostingRequest;
use App\Http\Resources\JobPostingResource;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;

/** @property JobPosting $model */
class JobPostingController extends BaseController
{
    /** @var JobPosting */
    protected $model;

    protected string $resourceClass = JobPostingResource::class;

    protected string $storeRequestClass = StoreJobPostingRequest::class;

    protected string $updateRequestClass = UpdateJobPostingRequest::class;

    public function __construct()
    {
        $this->model = new JobPosting;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return JobPostingResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? JobPosting::STATUS_DRAFT;
        $data['openings'] = $data['openings'] ?? 1;

        $posting = $this->model->create($data);

        return (new JobPostingResource($posting))->response()->setStatusCode(201);
    }

    public function publish(int $id): JsonResponse
    {
        $posting = $this->model->findOrFail($id);

        if ($posting->status !== JobPosting::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft posting can be published.',
            ], 422);
        }

        $posting->update([
            'status' => JobPosting::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return (new JobPostingResource($posting->fresh()))->response();
    }

    public function close(int $id): JsonResponse
    {
        $posting = $this->model->findOrFail($id);

        if ($posting->status !== JobPosting::STATUS_PUBLISHED) {
            return response()->json([
                'message' => 'Only a published posting can be closed.',
            ], 422);
        }

        $posting->update(['status' => JobPosting::STATUS_CLOSED]);

        return (new JobPostingResource($posting->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'department', 'employment_type'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'location'];
    }
}
