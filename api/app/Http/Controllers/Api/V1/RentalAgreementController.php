<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreRentalAgreementRequest;
use App\Http\Requests\Api\V1\UpdateRentalAgreementRequest;
use App\Http\Resources\RentalAgreementResource;
use App\Models\RentalAgreement;
use App\Services\RentalAgreementService;
use Illuminate\Http\JsonResponse;

class RentalAgreementController extends BaseController
{
    /** @var RentalAgreement */
    protected $model;

    protected string $resourceClass = RentalAgreementResource::class;

    protected string $storeRequestClass = StoreRentalAgreementRequest::class;

    protected string $updateRequestClass = UpdateRentalAgreementRequest::class;

    private RentalAgreementService $service;

    public function __construct()
    {
        $this->model = new RentalAgreement;
        $this->service = app(RentalAgreementService::class);
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['contact', 'items']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('start_at')->paginate(min($perPage, 100));

        return RentalAgreementResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $agreement = $this->model->with(['contact', 'items'])->findOrFail($id);

        return (new RentalAgreementResource($agreement))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['agreement_number'] = $this->service->generateNumber((int) $data['company_id']);
        $data['status'] = $data['status'] ?? RentalAgreement::STATUS_DRAFT;

        $agreement = $this->model->create($data);

        return (new RentalAgreementResource($agreement->load(['contact', 'items'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $agreement = $this->model->findOrFail($id);

        if (in_array($agreement->status, [RentalAgreement::STATUS_RETURNED, RentalAgreement::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'A returned or cancelled agreement cannot be edited.',
            ], 422);
        }

        $request = app($this->updateRequestClass);
        $agreement->update($request->validated());

        return (new RentalAgreementResource($agreement->fresh(['contact', 'items'])))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $agreement = $this->model->findOrFail($id);

        if ($agreement->status !== RentalAgreement::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft agreements can be deleted.',
            ], 422);
        }

        $agreement->delete();

        return response()->json(null, 204);
    }

    public function activate(int $id): JsonResponse
    {
        $agreement = $this->model->findOrFail($id);

        $agreement = $this->service->activate($agreement);

        return (new RentalAgreementResource($agreement->load(['contact', 'items'])))->response();
    }

    public function cancel(int $id): JsonResponse
    {
        $agreement = $this->model->findOrFail($id);

        $agreement = $this->service->cancel($agreement);

        return (new RentalAgreementResource($agreement->load(['contact', 'items'])))->response();
    }

    /**
     * What is out, what is late, and what is held as deposit.
     */
    public function summary(): JsonResponse
    {
        $companyId = (int) auth('api')->user()?->company_id;

        $rows = RentalAgreement::query()
            ->selectRaw('status, count(*) as total')
            ->where('company_id', $companyId)
            ->groupBy('status')
            ->get();

        $byStatus = array_fill_keys(RentalAgreement::STATUSES, 0);

        foreach ($rows as $row) {
            $byStatus[(string) $row->getAttribute('status')] = (int) $row->getAttribute('total');
        }

        $overdue = (int) RentalAgreement::query()
            ->where('company_id', $companyId)
            ->where('status', RentalAgreement::STATUS_ACTIVE)
            ->where('end_at', '<', now()->startOfDay())
            ->count();

        $depositHeld = (float) RentalAgreement::query()
            ->where('company_id', $companyId)
            ->where('status', RentalAgreement::STATUS_ACTIVE)
            ->sum('deposit_amount');

        return response()->json([
            'summary' => [
                'total_agreements' => array_sum($byStatus),
                'by_status' => $byStatus,
                'overdue' => $overdue,
                'deposit_held' => round($depositHeld, 2),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'contact_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['agreement_number', 'notes'];
    }
}
