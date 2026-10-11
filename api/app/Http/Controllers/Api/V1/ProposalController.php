<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProposalRequest;
use App\Http\Requests\Api\V1\UpdateProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Invoice;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProposalController extends BaseController
{
    /** @var Proposal */
    protected $model;

    protected string $resourceClass = ProposalResource::class;

    protected string $storeRequestClass = StoreProposalRequest::class;

    protected string $updateRequestClass = UpdateProposalRequest::class;

    public function __construct()
    {
        $this->model = new Proposal;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters(
            $this->model->query()->with('contact')->withCount('items')
        );

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return ProposalResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Proposal::STATUS_DRAFT;
        $data['proposal_number'] = $data['proposal_number'] ?? $this->generateNumber();

        $proposal = $this->model->create($data);
        $proposal->recalculateTotals();

        return (new ProposalResource($proposal->load('contact')))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $proposal = $this->model->with(['contact', 'items', 'invoice'])->findOrFail($id);

        return (new ProposalResource($proposal))->response();
    }

    public function send(int $id): JsonResponse
    {
        $proposal = $this->model->findOrFail($id);

        if ($proposal->status !== Proposal::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft proposal can be sent.',
            ], 422);
        }

        $proposal->update(['status' => Proposal::STATUS_SENT]);

        return (new ProposalResource($proposal->fresh()))->response();
    }

    public function accept(int $id): JsonResponse
    {
        $proposal = $this->model->findOrFail($id);

        if ($proposal->status !== Proposal::STATUS_SENT) {
            return response()->json([
                'message' => 'Only a sent proposal can be accepted.',
            ], 422);
        }

        $proposal->update(['status' => Proposal::STATUS_ACCEPTED]);

        return (new ProposalResource($proposal->fresh()))->response();
    }

    public function reject(int $id): JsonResponse
    {
        $proposal = $this->model->findOrFail($id);

        if ($proposal->status !== Proposal::STATUS_SENT) {
            return response()->json([
                'message' => 'Only a sent proposal can be rejected.',
            ], 422);
        }

        $proposal->update(['status' => Proposal::STATUS_REJECTED]);

        return (new ProposalResource($proposal->fresh()))->response();
    }

    public function convert(int $id): JsonResponse
    {
        $proposal = $this->model->with('items')->findOrFail($id);

        if ($proposal->status !== Proposal::STATUS_ACCEPTED) {
            return response()->json([
                'message' => 'Only an accepted proposal can be converted.',
            ], 422);
        }

        DB::transaction(function () use ($proposal) {
            $invoice = Invoice::create([
                'company_id' => $proposal->company_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'type' => 'invoice',
                'status' => 'draft',
                'contact_id' => $proposal->contact_id,
                'currency' => $proposal->currency,
                'issue_date' => now()->toDateString(),
                'subtotal' => $proposal->subtotal,
                'tax_amount' => $proposal->tax_amount,
                'discount_amount' => $proposal->discount_amount,
                'total' => $proposal->total,
                'paid_amount' => 0,
                'balance_due' => $proposal->total,
                'notes' => $proposal->notes,
                'terms' => $proposal->terms,
            ]);

            $invoice->items()->createMany(
                $proposal->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'subtotal' => $item->total,
                    'total' => $item->total,
                ])->all()
            );

            $proposal->update([
                'invoice_id' => $invoice->id,
                'status' => Proposal::STATUS_CONVERTED,
            ]);
        });

        return (new ProposalResource($proposal->fresh(['contact', 'invoice.items'])))->response();
    }

    protected function generateNumber(): string
    {
        return 'PROP-'.now()->format('Ymd').'-'.str_pad((string) (Proposal::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function generateInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.str_pad((string) (Invoice::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'contact_id', 'currency'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'proposal_number'];
    }
}
