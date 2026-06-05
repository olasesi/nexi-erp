<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/** @property Company $model */
class CompanyController extends BaseController
{
    protected $model;

    protected string $resourceClass = CompanyResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreCompanyRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateCompanyRequest';

    public function __construct()
    {
        $this->model = new Company;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    protected function getFilterableFields(): array
    {
        return ['is_active'];
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'legal_name', 'email', 'tax_id'];
    }
}
