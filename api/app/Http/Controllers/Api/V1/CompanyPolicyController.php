<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCompanyPolicyRequest;
use App\Http\Requests\Api\V1\UpdateCompanyPolicyRequest;
use App\Http\Resources\CompanyPolicyResource;
use App\Models\CompanyPolicy;
use Illuminate\Http\JsonResponse;

class CompanyPolicyController extends BaseController
{
    /** @var CompanyPolicy */
    protected $model;

    protected string $resourceClass = CompanyPolicyResource::class;

    protected string $storeRequestClass = StoreCompanyPolicyRequest::class;

    protected string $updateRequestClass = UpdateCompanyPolicyRequest::class;

    public function __construct()
    {
        $this->model = new CompanyPolicy;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return CompanyPolicyResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? CompanyPolicy::STATUS_DRAFT;

        $policy = $this->model->create($data);

        return (new CompanyPolicyResource($policy))->response()->setStatusCode(201);
    }

    public function publish(int $id): JsonResponse
    {
        $policy = $this->model->findOrFail($id);

        if ($policy->status !== CompanyPolicy::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft policy can be published.',
            ], 422);
        }

        $policy->update([
            'status' => CompanyPolicy::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return (new CompanyPolicyResource($policy->fresh()))->response();
    }

    public function archive(int $id): JsonResponse
    {
        $policy = $this->model->findOrFail($id);

        if ($policy->status !== CompanyPolicy::STATUS_PUBLISHED) {
            return response()->json([
                'message' => 'Only a published policy can be archived.',
            ], 422);
        }

        $policy->update(['status' => CompanyPolicy::STATUS_ARCHIVED]);

        return (new CompanyPolicyResource($policy->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'content'];
    }
}
