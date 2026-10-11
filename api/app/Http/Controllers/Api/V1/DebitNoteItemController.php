<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDebitNoteItemRequest;
use App\Http\Requests\Api\V1\UpdateDebitNoteItemRequest;
use App\Http\Resources\DebitNoteItemResource;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use Illuminate\Http\JsonResponse;

class DebitNoteItemController extends BaseController
{
    /** @var DebitNoteItem */
    protected $model;

    protected string $resourceClass = DebitNoteItemResource::class;

    protected string $storeRequestClass = StoreDebitNoteItemRequest::class;

    protected string $updateRequestClass = UpdateDebitNoteItemRequest::class;

    public function __construct()
    {
        $this->model = new DebitNoteItem;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('debitNote'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return DebitNoteItemResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $item = $this->model->create($data);
        $this->recalculateParent($item);

        return (new DebitNoteItemResource($item))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $item->update($request->validated());
        $this->recalculateParent($item);

        return (new DebitNoteItemResource($item->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);
        $parentId = (int) $item->debit_note_id;
        $item->delete();

        $note = DebitNote::query()->find($parentId);
        $note?->recalculateTotals();
        $note?->save();

        return response()->json(null, 204);
    }

    private function recalculateParent(DebitNoteItem $item): void
    {
        if ($note = $item->debitNote) {
            $note->recalculateTotals();
            $note->save();
        }
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'debit_note_id', 'product_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['description'];
    }
}
