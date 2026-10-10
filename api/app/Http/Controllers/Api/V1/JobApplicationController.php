<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreJobApplicationRequest;
use App\Http\Requests\Api\V1\UpdateJobApplicationRequest;
use App\Http\Resources\JobApplicationResource;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;

class JobApplicationController extends BaseController
{
    /** @var JobApplication */
    protected $model;

    protected string $resourceClass = JobApplicationResource::class;

    protected string $storeRequestClass = StoreJobApplicationRequest::class;

    protected string $updateRequestClass = UpdateJobApplicationRequest::class;

    public function __construct()
    {
        $this->model = new JobApplication;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return JobApplicationResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? JobApplication::STATUS_RECEIVED;

        $item = $this->model->create($data);

        return (new JobApplicationResource($item))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'job_posting_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'email'];
    }
}
