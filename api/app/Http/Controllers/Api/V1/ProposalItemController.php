<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProposalItemRequest;
use App\Http\Requests\Api\V1\UpdateProposalItemRequest;
use App\Http\Resources\ProposalItemResource;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Http\JsonResponse;

class ProposalItemController extends BaseController
{
    /** @var ProposalItem */
    protected $model;

    protected string $resourceClass = ProposalItemResource::class;

    protected string $storeRequestClass = StoreProposalItemRequest::class;

    protected string $updateRequestClass = UpdateProposalItemRequest::class;

    public function __construct()
    {
        $this->model = new ProposalItem;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('proposal'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return ProposalItemResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $item = $this->model->create($data);

        $item->proposal?->recalculateTotals();

        return (new ProposalItemResource($item))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $item->update($request->validated());

        $item->proposal?->recalculateTotals();

        return (new ProposalItemResource($item->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);
        $parentId = $item->proposal_id;

        $item->delete();

        if ($parent = Proposal::find($parentId)) {
            $parent->recalculateTotals();
        }

        return response()->json(null, 204);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'proposal_id', 'product_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['description'];
    }
}
