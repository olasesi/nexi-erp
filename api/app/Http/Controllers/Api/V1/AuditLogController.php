<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

class AuditLogController extends BaseController
{
    /** @var AuditLog */
    protected $model;

    protected string $resourceClass = AuditLogResource::class;

    public function __construct()
    {
        $this->model = new AuditLog;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->latest();

        if (request()->has('event')) {
            $query->where('event', request('event'));
        }

        if (request()->has('auditable_type')) {
            $query->where('auditable_type', request('auditable_type'));
        }

        if (request()->has('user_id')) {
            $query->where('user_id', request('user_id'));
        }

        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return AuditLogResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        return (new AuditLogResource($item))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id'];
    }
}
