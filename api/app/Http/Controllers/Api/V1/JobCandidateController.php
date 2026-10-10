<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreJobCandidateRequest;
use App\Http\Requests\Api\V1\UpdateJobCandidateRequest;
use App\Http\Resources\JobCandidateResource;
use App\Models\JobCandidate;
use Illuminate\Http\JsonResponse;

/** @property JobCandidate $model */
class JobCandidateController extends BaseController
{
    /** @var JobCandidate */
    protected $model;

    protected string $resourceClass = JobCandidateResource::class;

    protected string $storeRequestClass = StoreJobCandidateRequest::class;

    protected string $updateRequestClass = UpdateJobCandidateRequest::class;

    public function __construct()
    {
        $this->model = new JobCandidate;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('posting');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return JobCandidateResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['stage'] = $data['stage'] ?? JobCandidate::STAGE_APPLIED;

        $candidate = $this->model->create($data);

        return (new JobCandidateResource($candidate))->response()->setStatusCode(201);
    }

    public function hire(int $id): JsonResponse
    {
        $candidate = $this->model->findOrFail($id);

        if (in_array($candidate->stage, [JobCandidate::STAGE_HIRED, JobCandidate::STAGE_REJECTED], true)) {
            return response()->json([
                'message' => 'This candidate has already been finalised.',
            ], 422);
        }

        $candidate->update([
            'stage' => JobCandidate::STAGE_HIRED,
            'hired_at' => now(),
        ]);

        return (new JobCandidateResource($candidate->fresh()))->response();
    }

    public function reject(int $id): JsonResponse
    {
        $candidate = $this->model->findOrFail($id);

        if (in_array($candidate->stage, [JobCandidate::STAGE_HIRED, JobCandidate::STAGE_REJECTED], true)) {
            return response()->json([
                'message' => 'This candidate has already been finalised.',
            ], 422);
        }

        $candidate->update(['stage' => JobCandidate::STAGE_REJECTED]);

        return (new JobCandidateResource($candidate->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'job_posting_id', 'stage'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'email'];
    }
}
