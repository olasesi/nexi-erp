<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCreditNoteRequest;
use App\Http\Requests\Api\V1\UpdateCreditNoteRequest;
use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use Illuminate\Http\JsonResponse;

class CreditNoteController extends BaseController
{
    /** @var CreditNote */
    protected $model;

    protected string $resourceClass = CreditNoteResource::class;

    protected string $storeRequestClass = StoreCreditNoteRequest::class;

    protected string $updateRequestClass = UpdateCreditNoteRequest::class;

    public function __construct()
    {
        $this->model = new CreditNote;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->withCount('items'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return CreditNoteResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? CreditNote::STATUS_DRAFT;

        $note = $this->model->create($data);
        $note->recalculateTotals();

        return (new CreditNoteResource($note->fresh()))->response()->setStatusCode(201);
    }

    public function approve(int $id): JsonResponse
    {
        $note = $this->model->findOrFail($id);

        if ($note->status !== CreditNote::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft credit note can be approved.',
            ], 422);
        }

        $note->update([
            'status' => CreditNote::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        return (new CreditNoteResource($note->fresh()))->response();
    }

    public function apply(int $id): JsonResponse
    {
        $note = $this->model->findOrFail($id);

        if ($note->status !== CreditNote::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Only an approved credit note can be applied.',
            ], 422);
        }

        $note->update([
            'status' => CreditNote::STATUS_APPLIED,
            'applied_amount' => $note->total,
            'balance' => 0,
        ]);

        return (new CreditNoteResource($note->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'contact_id', 'sales_return_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['credit_note_number', 'reason'];
    }
}
