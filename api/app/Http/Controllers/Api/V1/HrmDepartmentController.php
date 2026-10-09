<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHrmDepartmentRequest;
use App\Http\Requests\Api\V1\UpdateHrmDepartmentRequest;
use App\Http\Resources\HrmDepartmentResource;
use App\Models\HrmDepartment;
use Illuminate\Http\JsonResponse;

class HrmDepartmentController extends BaseController
{
    /** @var HrmDepartment */
    protected $model;

    protected string $resourceClass = HrmDepartmentResource::class;

    protected string $storeRequestClass = StoreHrmDepartmentRequest::class;

    protected string $updateRequestClass = UpdateHrmDepartmentRequest::class;

    public function __construct()
    {
        $this->model = new HrmDepartment;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()
            ->with('manager')
            ->withCount(['employees', 'designations']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return HrmDepartmentResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->with('manager')
            ->withCount(['employees', 'designations'])
            ->findOrFail($id);

        return (new HrmDepartmentResource($item))->response();
    }

    /**
     * Employees and designations keep their department id as a null-on-delete
     * reference, so an empty department is the only safe thing to remove.
     */
    public function destroy(int $id): JsonResponse
    {
        $department = $this->model->findOrFail($id);

        if ($department->employees()->exists() || $department->designations()->exists()) {
            return response()->json([
                'message' => 'Move the employees and designations out of this department before deleting it.',
            ], 422);
        }

        $department->delete();

        return response()->json(null, 204);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'code', 'description'];
    }
}
