<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSalesAgentRequest;
use App\Http\Requests\Api\V1\UpdateSalesAgentRequest;
use App\Http\Resources\SalesAgentResource;
use App\Models\SalesAgent;
use Illuminate\Http\JsonResponse;

class SalesAgentController extends BaseController
{
    /** @var SalesAgent */
    protected $model;

    protected string $resourceClass = SalesAgentResource::class;

    protected string $storeRequestClass = StoreSalesAgentRequest::class;

    protected string $updateRequestClass = UpdateSalesAgentRequest::class;

    public function __construct()
    {
        $this->model = new SalesAgent;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('contact'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return SalesAgentResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? SalesAgent::STATUS_PENDING;

        $agent = $this->model->create($data);

        return (new SalesAgentResource($agent->load('contact')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Move a pending agent into the requested state.
     */
    public function request(int $id): JsonResponse
    {
        $agent = $this->model->findOrFail($id);

        if ($agent->status !== SalesAgent::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only a pending agent can be requested.',
            ], 422);
        }

        $agent->update([
            'status' => SalesAgent::STATUS_REQUESTED,
            'requested_at' => now(),
        ]);

        return (new SalesAgentResource($agent->fresh()))->response();
    }

    /**
     * Approve a requested agent, activating them for orders.
     */
    public function accept(int $id): JsonResponse
    {
        $agent = $this->model->findOrFail($id);

        if ($agent->status !== SalesAgent::STATUS_REQUESTED) {
            return response()->json([
                'message' => 'Only a requested agent can be accepted.',
            ], 422);
        }

        $agent->update([
            'status' => SalesAgent::STATUS_ACTIVE,
            'approved_at' => now(),
        ]);

        return (new SalesAgentResource($agent->fresh()))->response();
    }

    /**
     * Decline a requested agent.
     */
    public function reject(int $id): JsonResponse
    {
        $agent = $this->model->findOrFail($id);

        if ($agent->status !== SalesAgent::STATUS_REQUESTED) {
            return response()->json([
                'message' => 'Only a requested agent can be rejected.',
            ], 422);
        }

        $agent->update(['status' => SalesAgent::STATUS_REJECTED]);

        return (new SalesAgentResource($agent->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'user_id', 'contact_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'email', 'phone'];
    }
}
