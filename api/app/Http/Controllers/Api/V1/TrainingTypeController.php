<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTrainingTypeRequest;
use App\Http\Requests\Api\V1\UpdateTrainingTypeRequest;
use App\Http\Resources\TrainingTypeResource;
use App\Models\TrainingType;
use Illuminate\Http\JsonResponse;

class TrainingTypeController extends BaseController
{
    /** @var TrainingType */
    protected $model;

    protected string $resourceClass = TrainingTypeResource::class;

    protected string $storeRequestClass = StoreTrainingTypeRequest::class;

    protected string $updateRequestClass = UpdateTrainingTypeRequest::class;

    public function __construct()
    {
        $this->model = new TrainingType;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return TrainingTypeResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'branch', 'department'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
