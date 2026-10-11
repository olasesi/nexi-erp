<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCreditNoteItemRequest;
use App\Http\Requests\Api\V1\UpdateCreditNoteItemRequest;
use App\Http\Resources\CreditNoteItemResource;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use Illuminate\Http\JsonResponse;

class CreditNoteItemController extends BaseController
{
    /** @var CreditNoteItem */
    protected $model;

    protected string $resourceClass = CreditNoteItemResource::class;

    protected string $storeRequestClass = StoreCreditNoteItemRequest::class;

    protected string $updateRequestClass = UpdateCreditNoteItemRequest::class;

    public function __construct()
    {
        $this->model = new CreditNoteItem;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('creditNote'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return CreditNoteItemResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $item = $this->model->create($data);

        $item->creditNote?->recalculateTotals();

        return (new CreditNoteItemResource($item->fresh('creditNote')))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $item->update($request->validated());

        $item->creditNote?->recalculateTotals();

        return (new CreditNoteItemResource($item->fresh('creditNote')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);
        $creditNoteId = $item->credit_note_id;

        $item->delete();

        CreditNote::find($creditNoteId)?->recalculateTotals();

        return response()->json(null, 204);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'credit_note_id', 'product_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['description'];
    }
}
