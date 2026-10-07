<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CrmStageResource;
use App\Models\CrmStage;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;

/** @property CrmStage $model */
class CrmStageController extends BaseController
{
    protected $model;

    protected string $resourceClass = CrmStageResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreCrmStageRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateCrmStageRequest';

    public function __construct()
    {
        $this->model = new CrmStage;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->withCount('deals')
            ->orderBy('position')
            ->orderBy('name');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        // Stages are an ordered pipeline; an omitted position keeps appending
        // to the end rather than collapsing everything onto the default.
        if (! isset($data['position'])) {
            $data['position'] = $this->model->where('company_id', $data['company_id'])->max('position') + 1;
        }

        $item = $this->model->create($data);

        return (new $this->resourceClass($item))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->withCount('deals')->findOrFail($id);

        return (new $this->resourceClass($item))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $stage = $this->model->findOrFail($id);

        // The foreign key is restrictOnDelete, so surface the reason as a
        // readable 422 instead of letting the driver raise a query exception.
        if (Deal::where('crm_stage_id', $stage->id)->exists()) {
            return response()->json([
                'message' => 'Move the deals off this stage before deleting it.',
            ], 422);
        }

        $stage->delete();

        return response()->json(null, 204);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
