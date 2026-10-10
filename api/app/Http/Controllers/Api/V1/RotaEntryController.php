<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreRotaEntryRequest;
use App\Http\Requests\Api\V1\UpdateRotaEntryRequest;
use App\Http\Resources\RotaEntryResource;
use App\Models\RotaEntry;
use Illuminate\Http\JsonResponse;

class RotaEntryController extends BaseController
{
    /** @var RotaEntry */
    protected $model;

    protected string $resourceClass = RotaEntryResource::class;

    protected string $storeRequestClass = StoreRotaEntryRequest::class;

    protected string $updateRequestClass = UpdateRotaEntryRequest::class;

    public function __construct()
    {
        $this->model = new RotaEntry;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['rota', 'employee']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('shift_date')->paginate(min($perPage, 100));

        return RotaEntryResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'rota_id', 'employee_id', 'shift_type', 'shift_date'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['notes'];
    }
}
