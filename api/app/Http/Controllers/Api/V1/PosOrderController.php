<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PosOrderStatus;
use App\Http\Requests\Api\V1\StorePosOrderRequest;
use App\Http\Requests\Api\V1\UpdatePosOrderRequest;
use App\Http\Resources\PosOrderResource;
use App\Models\PosOrder;
use App\Services\OrderService;
use App\Services\PosAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** @property PosOrder $model */
class PosOrderController extends BaseController
{
    protected $model;

    protected string $resourceClass = PosOrderResource::class;

    protected string $storeRequestClass = StorePosOrderRequest::class;

    protected string $updateRequestClass = UpdatePosOrderRequest::class;

    /** @var list<string> */
    private const INCLUDES = ['items', 'payments', 'contact', 'register', 'cashier'];

    public function __construct(private readonly PosAnalytics $analytics)
    {
        $this->model = new PosOrder;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(self::INCLUDES);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->latest('id')->paginate(min($perPage, 100));

        return PosOrderResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $items = $data['items'] ?? [];
        $payments = $data['payments'] ?? [];
        unset($data['items'], $data['payments']);

        $lines = app(OrderService::class)->buildLines($items);
        $totals = app(OrderService::class)->computeTotals($lines, (float) ($data['discount_rate'] ?? 0));

        $data['order_number'] = $this->generateOrderNumber();
        $data['status'] = $data['status'] ?? PosOrderStatus::Completed->value;
        $data['order_date'] = $data['order_date'] ?? now()->toDateString();
        $data['cashier_id'] = $data['cashier_id'] ?? auth('api')->user()?->id;

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $paid = $this->tendered($payments);
        $data['paid_amount'] = $paid;
        $data['balance_due'] = round($totals['total'] - $paid, 2);
        $data['payment_status'] = $this->paymentStatus((float) $totals['total'], $paid);

        $order = DB::transaction(function () use ($data, $totals, $lines, $payments) {
            $order = $this->model->create(array_merge($data, $totals));
            $order->items()->createMany($lines);
            $this->recordPayments($order, $payments);

            return $order;
        });

        $order->load(self::INCLUDES);

        return (new PosOrderResource($order))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $order = $this->model->findOrFail($id);
        $data = $request->validated();

        $items = $data['items'] ?? null;
        $payments = $data['payments'] ?? null;
        unset($data['items'], $data['payments']);

        $discountRate = (float) ($data['discount_rate'] ?? $order->discount_rate);

        DB::transaction(function () use ($order, $data, $items, $payments, $discountRate) {
            if ($items !== null) {
                $lines = app(OrderService::class)->buildLines($items);
                $order->update(array_merge($data, app(OrderService::class)->computeTotals($lines, $discountRate)));
                app(OrderService::class)->syncItems($order, $items);
            } else {
                $order->update($data);
            }

            if ($payments !== null) {
                $order->payments()->delete();
                $this->recordPayments($order, $payments);
            }

            // Re-derive the tender columns from whatever payments now exist.
            $order->refresh();
            $paid = round((float) $order->payments()->sum('amount'), 2);
            $order->update([
                'paid_amount' => $paid,
                'balance_due' => round((float) $order->total - $paid, 2),
                'payment_status' => $this->paymentStatus((float) $order->total, $paid),
            ]);
        });

        return (new PosOrderResource($order->fresh(self::INCLUDES)))->response();
    }

    /**
     * Till summary for one trading day: takings, transaction count, the
     * payment mix and the best sellers. Registered on the POS module so the
     * dashboard card and the POS home read the same numbers.
     */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'date' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $companyId = $data['company_id'] ?? auth('api')->user()?->company_id;

        if ($companyId === null) {
            return response()->json([
                'message' => 'A company_id is required for the POS summary.',
            ], 422);
        }

        $companyId = (int) $companyId;
        $limit = (int) ($data['limit'] ?? 5);

        return response()->json(array_merge([
            'company_id' => $companyId,
            'limit' => $limit,
        ], $this->analytics->summary($companyId, $data['date'] ?? null, $limit)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $payments
     */
    private function recordPayments(PosOrder $order, array $payments): void
    {
        foreach ($payments as $payment) {
            $order->payments()->create([
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'reference' => $payment['reference'] ?? null,
                'paid_at' => $payment['paid_at'] ?? now(),
                'notes' => $payment['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $payments
     */
    private function tendered(array $payments): float
    {
        return round(array_sum(array_map(
            static fn (array $payment): float => (float) ($payment['amount'] ?? 0),
            $payments,
        )), 2);
    }

    private function paymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return PosOrder::PAYMENT_PENDING;
        }

        if ($total <= 0 || $paid >= $total) {
            return PosOrder::PAYMENT_PAID;
        }

        return PosOrder::PAYMENT_PARTIAL;
    }

    protected function generateOrderNumber(): string
    {
        return 'POS-'.now()->format('Ymd').'-'.str_pad((string) (PosOrder::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'payment_status', 'register_id', 'contact_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['order_number'];
    }
}
