<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Requests\Api\V1\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class EventController extends BaseController
{
    /** @var Event */
    protected $model;

    protected string $resourceClass = EventResource::class;

    protected string $storeRequestClass = StoreEventRequest::class;

    protected string $updateRequestClass = UpdateEventRequest::class;

    public function __construct()
    {
        $this->model = new Event;
    }

    public function index(): JsonResponse
    {
        request()->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $query = $this->model->query()->with('creator')->withCount('attendees');
        $query = $this->applyFilters($query);

        if ($from = request('from')) {
            $query->where('starts_at', '>=', $from);
        }

        if ($to = request('to')) {
            $query->where('starts_at', '<=', $to);
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('starts_at')->paginate(min($perPage, 100));

        return EventResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $event = $this->model->with('creator')->withCount('attendees')->findOrFail($id);

        return (new EventResource($event))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['type'] = $data['type'] ?? Event::TYPE_MEETING;
        $data['all_day'] = $data['all_day'] ?? false;
        $data['status'] = $data['status'] ?? Event::STATUS_SCHEDULED;

        $event = $this->model->create($data);

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    public function cancel(int $id): JsonResponse
    {
        $event = $this->model->findOrFail($id);

        if ($event->status === Event::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'This event is already cancelled.',
            ], 422);
        }

        $event->update(['status' => Event::STATUS_CANCELLED]);

        return (new EventResource($event->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'type', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'location'];
    }
}
