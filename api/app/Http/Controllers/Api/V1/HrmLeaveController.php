<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHrmLeaveRequest;
use App\Http\Requests\Api\V1\UpdateHrmLeaveRequest;
use App\Http\Resources\HrmLeaveResource;
use App\Models\HrmLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HrmLeaveController extends BaseController
{
    /** @var HrmLeave */
    protected $model;

    protected string $resourceClass = HrmLeaveResource::class;

    protected string $storeRequestClass = StoreHrmLeaveRequest::class;

    protected string $updateRequestClass = UpdateHrmLeaveRequest::class;

    public function __construct()
    {
        $this->model = new HrmLeave;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('employee');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('start_date')->orderByDesc('id')->paginate(min($perPage, 100));

        return HrmLeaveResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['status'] = $data['status'] ?? HrmLeave::STATUS_PENDING;
        $data['days'] = $data['days'] ?? $this->inclusiveDays($data['start_date'], $data['end_date']);

        // A request that arrives already decided carries its reviewer too, so
        // the trail is never left half stamped.
        if ($data['status'] !== HrmLeave::STATUS_PENDING && ($user = auth('api')->user())) {
            $data['reviewed_by'] = $user->id;
            $data['reviewed_at'] = now();
        }

        $item = $this->model->with('employee')->create($data);

        return (new HrmLeaveResource($item))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->with('employee')->findOrFail($id);

        return (new HrmLeaveResource($item))->response();
    }

    /**
     * Reviewed requests are frozen: dates and status only move through the
     * approve and reject actions, which stamp who decided and when.
     */
    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);

        if (! $item->isPending()) {
            return response()->json([
                'message' => 'A reviewed leave request cannot be edited.',
            ], 422);
        }

        $data = $request->validated();

        if (isset($data['start_date'], $data['end_date']) && ! isset($data['days'])) {
            $data['days'] = $this->inclusiveDays($data['start_date'], $data['end_date']);
        }

        $item->update($data);

        return (new HrmLeaveResource($item->fresh('employee')))->response();
    }

    /**
     * Sign off a pending request. The reviewer and timestamp are stamped here
     * so the approval trail never depends on what the client sends.
     */
    public function approve(int $id): JsonResponse
    {
        return $this->review($id, HrmLeave::STATUS_APPROVED);
    }

    /**
     * Refuse a pending request, keeping the reason when one is supplied.
     */
    public function reject(int $id): JsonResponse
    {
        return $this->review($id, HrmLeave::STATUS_REJECTED);
    }

    private function review(int $id, string $status): JsonResponse
    {
        $leave = $this->model->with('employee')->findOrFail($id);

        if (! $leave->isPending()) {
            return response()->json([
                'message' => 'Only a pending leave request can be reviewed.',
            ], 422);
        }

        $leave->update([
            'status' => $status,
            'review_note' => request()->input('review_note'),
            'reviewed_by' => auth('api')->user()?->id,
            'reviewed_at' => now(),
        ]);

        return (new HrmLeaveResource($leave->fresh('employee')))->response();
    }

    /**
     * Calendar days covered by the request, both ends included.
     */
    private function inclusiveDays(mixed $start, mixed $end): float
    {
        $from = Carbon::parse((string) $start)->startOfDay();
        $to = Carbon::parse((string) $end)->startOfDay();

        return (float) $from->diffInDays($to) + 1;
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'employee_id', 'leave_type', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['reason', 'review_note'];
    }
}
