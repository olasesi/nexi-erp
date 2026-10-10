<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAssetAssignmentRequest;
use App\Http\Requests\Api\V1\UpdateAssetAssignmentRequest;
use App\Http\Resources\AssetAssignmentResource;
use App\Models\AssetAssignment;
use Illuminate\Http\JsonResponse;

class AssetAssignmentController extends BaseController
{
    /** @var AssetAssignment */
    protected $model;

    protected string $resourceClass = AssetAssignmentResource::class;

    protected string $storeRequestClass = StoreAssetAssignmentRequest::class;

    protected string $updateRequestClass = UpdateAssetAssignmentRequest::class;

    public function __construct()
    {
        $this->model = new AssetAssignment;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['asset', 'user', 'contact']);

        if (request('open') === '1') {
            $query->whereNull('returned_at');
        }

        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('assigned_at')->paginate(min($perPage, 100));

        return AssetAssignmentResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'asset_id', 'user_id'];
    }
}
