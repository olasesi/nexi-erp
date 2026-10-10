<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTimeEntryRequest;
use App\Http\Requests\Api\V1\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;

class TimeEntryController extends BaseController
{
    /** @var TimeEntry */
    protected $model;

    protected string $resourceClass = TimeEntryResource::class;

    protected string $storeRequestClass = StoreTimeEntryRequest::class;

    protected string $updateRequestClass = UpdateTimeEntryRequest::class;

    public function __construct()
    {
        $this->model = new TimeEntry;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('employee'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('started_at')->paginate(min($perPage, 100));

        return TimeEntryResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['started_at'] = $data['started_at'] ?? now();
        $data['is_billable'] = $data['is_billable'] ?? false;

        $item = $this->model->create($data);

        return (new TimeEntryResource($item->load('employee')))
            ->response()
            ->setStatusCode(201);
    }

    public function start(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if ($item->isRunning()) {
            return response()->json([
                'message' => 'This timer is already running.',
            ], 422);
        }

        $item->update([
            'started_at' => now(),
            'ended_at' => null,
            'duration_minutes' => null,
        ]);

        return (new TimeEntryResource($item->fresh('employee')))->response();
    }

    public function stop(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if (! $item->isRunning()) {
            return response()->json([
                'message' => 'This timer is not running.',
            ], 422);
        }

        $startedAt = $item->started_at;
        $endedAt = now();

        $item->update([
            'ended_at' => $endedAt,
            'duration_minutes' => $startedAt !== null ? (int) $startedAt->diffInMinutes($endedAt) : null,
        ]);

        return (new TimeEntryResource($item->fresh('employee')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'employee_id', 'is_billable'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['task', 'description'];
    }
}
