<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreGymMemberRequest;
use App\Http\Requests\Api\V1\UpdateGymMemberRequest;
use App\Http\Resources\GymMemberResource;
use App\Models\GymMember;
use App\Models\GymVisit;
use App\Services\GymService;
use Illuminate\Http\JsonResponse;

class GymMemberController extends BaseController
{
    /** @var GymMember */
    protected $model;

    protected string $resourceClass = GymMemberResource::class;

    protected string $storeRequestClass = StoreGymMemberRequest::class;

    protected string $updateRequestClass = UpdateGymMemberRequest::class;

    private GymService $service;

    public function __construct()
    {
        $this->model = new GymMember;
        $this->service = app(GymService::class);
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('plan');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('full_name')->paginate(min($perPage, 100));

        return GymMemberResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $member = $this->model->with('plan')->findOrFail($id);

        return (new GymMemberResource($member))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['member_number'] = $this->service->generateMemberNumber((int) $data['company_id']);
        $data['status'] = $data['status'] ?? GymMember::STATUS_ACTIVE;

        $member = $this->model->create($data);

        return (new GymMemberResource($member->load('plan')))
            ->response()
            ->setStatusCode(201);
    }

    public function suspend(int $id): JsonResponse
    {
        $member = $this->model->findOrFail($id);

        $member = $this->service->suspend($member);

        return (new GymMemberResource($member->load('plan')))->response();
    }

    public function activate(int $id): JsonResponse
    {
        $member = $this->model->findOrFail($id);

        $member = $this->service->activate($member);

        return (new GymMemberResource($member->load('plan')))->response();
    }

    /**
     * Front-desk snapshot: who can train, who lapsed, and how busy the floor is.
     */
    public function summary(): JsonResponse
    {
        $companyId = (int) auth('api')->user()?->company_id;

        $usable = (int) GymMember::query()
            ->where('company_id', $companyId)
            ->usable()
            ->count();

        $suspended = (int) GymMember::query()
            ->where('company_id', $companyId)
            ->where('status', GymMember::STATUS_SUSPENDED)
            ->count();

        $cancelled = (int) GymMember::query()
            ->where('company_id', $companyId)
            ->where('status', GymMember::STATUS_CANCELLED)
            ->count();

        $expired = (int) GymMember::query()
            ->where('company_id', $companyId)
            ->where('status', GymMember::STATUS_ACTIVE)
            ->where('renews_at', '<', now()->startOfDay())
            ->count();

        $visitsToday = (int) GymVisit::query()
            ->where('company_id', $companyId)
            ->whereDate('checked_in_at', now()->toDateString())
            ->count();

        $currentlyIn = (int) GymVisit::query()
            ->where('company_id', $companyId)
            ->whereNull('checked_out_at')
            ->count();

        return response()->json([
            'summary' => [
                'active' => $usable,
                'suspended' => $suspended,
                'cancelled' => $cancelled,
                'expired' => $expired,
                'visits_today' => $visitsToday,
                'currently_in' => $currentlyIn,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'plan_id', 'contact_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['full_name', 'member_number', 'email', 'phone'];
    }
}
