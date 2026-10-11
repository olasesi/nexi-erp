<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAwardRequest;
use App\Http\Requests\Api\V1\UpdateAwardRequest;
use App\Http\Resources\AwardResource;
use App\Models\Award;
use Illuminate\Http\JsonResponse;

class AwardController extends BaseController
{
    /** @var Award */
    protected $model;

    protected string $resourceClass = AwardResource::class;

    protected string $storeRequestClass = StoreAwardRequest::class;

    protected string $updateRequestClass = UpdateAwardRequest::class;

    public function __construct()
    {
        $this->model = new Award;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('employee');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('awarded_date')->paginate(min($perPage, 100));

        return AwardResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $award = $this->model->create($data);

        return (new AwardResource($award->load('employee')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'employee_id', 'award_type'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
