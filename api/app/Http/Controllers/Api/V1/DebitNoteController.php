<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDebitNoteRequest;
use App\Http\Requests\Api\V1\UpdateDebitNoteRequest;
use App\Http\Resources\DebitNoteResource;
use App\Models\DebitNote;
use Illuminate\Http\JsonResponse;

class DebitNoteController extends BaseController
{
    /** @var DebitNote */
    protected $model;

    protected string $resourceClass = DebitNoteResource::class;

    protected string $storeRequestClass = StoreDebitNoteRequest::class;

    protected string $updateRequestClass = UpdateDebitNoteRequest::class;

    public function __construct()
    {
        $this->model = new DebitNote;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->withCount('items'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return DebitNoteResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? DebitNote::STATUS_DRAFT;

        $note = $this->model->create($data);
        $note->recalculateTotals();
        $note->save();

        return (new DebitNoteResource($note->fresh()))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $note = $this->model->with('items')->findOrFail($id);

        return (new DebitNoteResource($note))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $note = $this->model->findOrFail($id);
        $note->update($request->validated());
        $note->recalculateTotals();
        $note->save();

        return (new DebitNoteResource($note->fresh()))->response();
    }

    public function approve(int $id): JsonResponse
    {
        $note = $this->model->findOrFail($id);

        if ($note->status !== DebitNote::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft debit note can be approved.',
            ], 422);
        }

        $note->update([
            'status' => DebitNote::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        return (new DebitNoteResource($note->fresh()))->response();
    }

    public function apply(int $id): JsonResponse
    {
        $note = $this->model->findOrFail($id);

        if ($note->status !== DebitNote::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Only an approved debit note can be applied.',
            ], 422);
        }

        $note->update([
            'status' => DebitNote::STATUS_APPLIED,
            'applied_amount' => $note->total,
            'balance' => 0,
        ]);

        return (new DebitNoteResource($note->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'contact_id', 'purchase_order_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['debit_note_number', 'reason'];
    }
}
