<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreGymVisitRequest;
use App\Http\Requests\Api\V1\UpdateGymVisitRequest;
use App\Http\Resources\GymVisitResource;
use App\Models\GymMember;
use App\Models\GymVisit;
use App\Services\GymService;
use Illuminate\Http\JsonResponse;

class GymVisitController extends BaseController
{
    /** @var GymVisit */
    protected $model;

    protected string $resourceClass = GymVisitResource::class;

    protected string $storeRequestClass = StoreGymVisitRequest::class;

    protected string $updateRequestClass = UpdateGymVisitRequest::class;

    private GymService $service;

    public function __construct()
    {
        $this->model = new GymVisit;
        $this->service = app(GymService::class);
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('member');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('checked_in_at')->paginate(min($perPage, 100));

        return GymVisitResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $visit = $this->model->with('member')->findOrFail($id);

        return (new GymVisitResource($visit))->response();
    }

    /**
     * Checking in is the act of opening a visit; the service enforces a live
     * membership and any monthly visit cap.
     */
    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $member = GymMember::findOrFail((int) $data['gym_member_id']);
        $staffId = auth('api')->id();

        $visit = $this->service->checkIn($member, $staffId === null ? null : (int) $staffId);

        return (new GymVisitResource($visit->load('member')))
            ->response()
            ->setStatusCode(201);
    }

    public function checkOut(int $id): JsonResponse
    {
        $visit = $this->model->findOrFail($id);

        $visit = $this->service->checkOut($visit);

        return (new GymVisitResource($visit->load('member')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $visit = $this->model->findOrFail($id);

        if ($visit->checked_out_at === null) {
            return response()->json([
                'message' => 'An open visit cannot be deleted; check the member out first.',
            ], 422);
        }

        $visit->delete();

        return response()->json(null, 204);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'gym_member_id', 'checked_in_by'];
    }
}
