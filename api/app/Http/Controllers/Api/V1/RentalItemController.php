<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ReturnRentalItemRequest;
use App\Http\Requests\Api\V1\StoreRentalItemRequest;
use App\Http\Requests\Api\V1\UpdateRentalItemRequest;
use App\Http\Resources\RentalItemResource;
use App\Models\RentalAgreement;
use App\Models\RentalItem;
use App\Services\RentalAgreementService;
use Illuminate\Http\JsonResponse;

class RentalItemController extends BaseController
{
    /** @var RentalItem */
    protected $model;

    protected string $resourceClass = RentalItemResource::class;

    protected string $storeRequestClass = StoreRentalItemRequest::class;

    protected string $updateRequestClass = UpdateRentalItemRequest::class;

    private RentalAgreementService $service;

    public function __construct()
    {
        $this->model = new RentalItem;
        $this->service = app(RentalAgreementService::class);
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('product');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return RentalItemResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->with('product')->findOrFail($id);

        return (new RentalItemResource($item))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $agreement = RentalAgreement::findOrFail((int) $data['rental_agreement_id']);

        if ($this->isClosed($agreement)) {
            return response()->json([
                'message' => 'Items cannot be added to a returned or cancelled agreement.',
            ], 422);
        }

        $data['company_id'] = $data['company_id'] ?? $agreement->company_id;

        $item = $this->model->create($data);

        return (new RentalItemResource($item->load('product')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if ($item->isReturned()) {
            return response()->json([
                'message' => 'A returned item cannot be edited.',
            ], 422);
        }

        if ($this->isClosed($item->agreement)) {
            return response()->json([
                'message' => 'Items on a returned or cancelled agreement cannot be edited.',
            ], 422);
        }

        $request = app($this->updateRequestClass);
        $item->update($request->validated());

        return (new RentalItemResource($item->fresh('product')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if ($item->isReturned()) {
            return response()->json([
                'message' => 'A returned item cannot be deleted.',
            ], 422);
        }

        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * Hand a single line item back; the service closes the agreement once every
     * item is in.
     */
    public function returnItem(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        $request = app(ReturnRentalItemRequest::class);
        $condition = $request->validated('return_condition');

        $item = $this->service->returnItem($item, $condition);

        return (new RentalItemResource($item->load('product')))->response();
    }

    private function isClosed(?RentalAgreement $agreement): bool
    {
        return $agreement !== null
            && in_array($agreement->status, [RentalAgreement::STATUS_RETURNED, RentalAgreement::STATUS_CANCELLED], true);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'rental_agreement_id', 'product_id'];
    }
}
