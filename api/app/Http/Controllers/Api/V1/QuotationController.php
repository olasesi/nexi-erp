<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\QuotationResource;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property Quotation $model */
class QuotationController extends BaseController
{
    protected $model;

    protected string $resourceClass = QuotationResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreQuotationRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateQuotationRequest';

    public function __construct()
    {
        $this->model = new Quotation;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['contact', 'items']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $items = $data['items'] ?? [];
        unset($data['items']);

        $lines = app(OrderService::class)->buildLines($items);
        $totals = app(OrderService::class)->computeTotals($lines);

        $data['quotation_no'] = $this->nextRef('QT', Quotation::class, 'quotation_no');
        $data['status'] = $data['status'] ?? 'draft';

        $quotation = DB::transaction(function () use ($data, $lines, $totals) {
            $quotation = $this->model->create(array_merge($data, $totals));
            $quotation->items()->createMany($lines);

            return $quotation;
        });

        $quotation->load(['contact', 'items']);

        return (new $this->resourceClass($quotation))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $quotation = $this->model->with(['contact', 'items'])->findOrFail($id);

        return (new $this->resourceClass($quotation))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $quotation = $this->model->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($quotation, $data) {
            if (isset($data['items'])) {
                $lines = app(OrderService::class)->buildLines($data['items']);
                $quotation->update(array_merge($data, app(OrderService::class)->computeTotals($lines)));
                app(OrderService::class)->syncItems($quotation, $data['items']);
            } else {
                $quotation->update($data);
            }
        });

        return (new $this->resourceClass($quotation->fresh(['contact', 'items'])))->response();
    }

    public function accept(int $id): JsonResponse
    {
        $quotation = $this->model->with('items')->findOrFail($id);

        if ($quotation->status === 'accepted') {
            return response()->json(['message' => 'Quotation is already accepted.'], 422);
        }

        if (in_array($quotation->status, ['rejected', 'expired'])) {
            return response()->json(['message' => 'Quotation cannot be accepted in its current status.'], 422);
        }

        DB::transaction(function () use ($quotation) {
            $quotation->update(['status' => 'accepted']);

            if (request()->boolean('convert_to_sales_order', true)) {
                $salesOrder = SalesOrder::create([
                    'company_id' => $quotation->company_id,
                    'contact_id' => $quotation->contact_id,
                    'warehouse_id' => $quotation->warehouse_id,
                    'order_number' => 'SO-'.now()->format('Ymd').'-'.str_pad((string) (SalesOrder::max('id') + 1), 5, '0', STR_PAD_LEFT),
                    'status' => 'draft',
                    'payment_status' => 'pending',
                    'subtotal' => $quotation->subtotal,
                    'tax_amount' => $quotation->tax_amount,
                    'discount_amount' => $quotation->discount_amount,
                    'total' => $quotation->total,
                    'paid_amount' => 0,
                    'balance_due' => $quotation->total,
                    'order_date' => now(),
                    'notes' => 'Generated from quotation '.$quotation->quotation_no,
                ]);

                $salesOrder->items()->createMany(
                    $quotation->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name,
                        'product_sku' => $item->product?->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_rate' => $item->tax_rate,
                        'tax_amount' => $item->tax_amount,
                        'discount_amount' => $item->discount_amount,
                        'subtotal' => $item->subtotal,
                        'total' => round((float) $item->subtotal + (float) $item->tax_amount - (float) $item->discount_amount, 2),
                    ])->all()
                );
            }
        });

        return (new $this->resourceClass($quotation->fresh(['contact', 'items'])))->response();
    }

    public function reject(int $id): JsonResponse
    {
        $quotation = $this->model->findOrFail($id);

        if ($quotation->status === 'rejected') {
            return response()->json(['message' => 'Quotation is already rejected.'], 422);
        }

        if ($quotation->status === 'accepted') {
            return response()->json(['message' => 'An accepted quotation cannot be rejected.'], 422);
        }

        $quotation->update(['status' => 'rejected']);

        return (new $this->resourceClass($quotation->fresh(['contact', 'items'])))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $quotation = $this->model->findOrFail($id);

        if ($quotation->status !== 'draft') {
            return response()->json(['message' => 'Only draft quotations can be deleted.'], 422);
        }

        $quotation->items()->delete();
        $quotation->delete();

        return response()->json(null, 204);
    }

    private function nextRef(string $prefix, $modelClass, string $column = 'reference_no'): string
    {
        $yymm = date('ym');

        $last = (clone $modelClass::query())
            ->where($column, 'like', "{$prefix}-{$yymm}-%")
            ->count();

        return sprintf('%s-%s-%04d', $prefix, $yymm, $last + 1);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'contact_id', 'status'];
    }

    protected function getSearchableFields(): array
    {
        return ['quotation_no'];
    }
}
