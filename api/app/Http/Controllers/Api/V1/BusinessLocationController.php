<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BusinessLocationResource;
use App\Models\BusinessLocation;
use App\Services\BusinessSettingsService;
use Illuminate\Http\JsonResponse;

/** @property BusinessLocation $model */
class BusinessLocationController extends BaseController
{
    protected $model;

    /** @var class-string<BusinessLocationResource> */
    protected string $resourceClass = BusinessLocationResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreBusinessLocationRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateBusinessLocationRequest';

    public function __construct()
    {
        $this->model = new BusinessLocation;
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $user = auth('api')->user();
        $data['company_id'] = $data['company_id'] ?? $user?->company_id;

        if (blank($data['location_id'] ?? null)) {
            $data['location_id'] = $this->nextLocationId($data['company_id']);
        }

        $item = $this->model->create($data);

        return (new $this->resourceClass($item))->response()->setStatusCode(201);
    }

    private function nextLocationId(?int $companyId): string
    {
        $prefix = app(BusinessSettingsService::class)->value($companyId, 'prefixes', 'business_location') ?: 'BL';

        $last = $this->model->newQuery()
            ->where('location_id', 'like', "{$prefix}-%")
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->count();

        return sprintf('%s-%04d', $prefix, $last + 1);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'city', 'state', 'country', 'price_group'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'location_id', 'landmark', 'city', 'state', 'country'];
    }
}
