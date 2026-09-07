<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ChartOfAccountResource;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;

/** @property ChartOfAccount $model */
class ChartOfAccountController extends BaseController
{
    protected $model;

    protected string $resourceClass = ChartOfAccountResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreChartOfAccountRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateChartOfAccountRequest';

    public function __construct()
    {
        $this->model = new ChartOfAccount;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()
            ->with('parent')
            ->orderBy('code');

        $query = $this->applyFilters($query);

        $all = request()->boolean('all');
        $perPage = request()->integer('per_page', 15);

        $items = $all ? $query->get() : $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'type', 'is_active'];
    }

    protected function getSearchableFields(): array
    {
        return ['code', 'name'];
    }
}
