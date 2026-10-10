<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreWorkPermitRequest;
use App\Http\Requests\Api\V1\UpdateWorkPermitRequest;
use App\Http\Resources\WorkPermitResource;
use App\Models\WorkPermit;
use Illuminate\Http\JsonResponse;

class WorkPermitController extends BaseController
{
    /** @var WorkPermit */
    protected $model;

    protected string $resourceClass = WorkPermitResource::class;

    protected string $storeRequestClass = StoreWorkPermitRequest::class;

    protected string $updateRequestClass = UpdateWorkPermitRequest::class;

    public function __construct()
    {
        $this->model = new WorkPermit;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('employee'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return WorkPermitResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? WorkPermit::STATUS_ACTIVE;

        $permit = $this->model->create($data);

        return (new WorkPermitResource($permit->load('employee')))
            ->response()
            ->setStatusCode(201);
    }

    public function renew(int $id): JsonResponse
    {
        $permit = $this->model->findOrFail($id);

        if ($permit->status === WorkPermit::STATUS_REVOKED) {
            return response()->json([
                'message' => 'A revoked permit cannot be renewed.',
            ], 422);
        }

        $data = request()->validate([
            'expiry_date' => 'required|date|after:now',
        ]);

        $permit->update([
            'issued_date' => today(),
            'expiry_date' => $data['expiry_date'],
            'status' => WorkPermit::STATUS_ACTIVE,
        ]);

        return (new WorkPermitResource($permit->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'employee_id', 'permit_type'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['permit_number', 'issuing_country'];
    }
}
