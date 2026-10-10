<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreEventAttendeeRequest;
use App\Http\Requests\Api\V1\UpdateEventAttendeeRequest;
use App\Http\Resources\EventAttendeeResource;
use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class EventAttendeeController extends BaseController
{
    /** @var EventAttendee */
    protected $model;

    protected string $resourceClass = EventAttendeeResource::class;

    protected string $storeRequestClass = StoreEventAttendeeRequest::class;

    protected string $updateRequestClass = UpdateEventAttendeeRequest::class;

    public function __construct()
    {
        $this->model = new EventAttendee;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('user');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('created_at')->paginate(min($perPage, 100));

        return EventAttendeeResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['status'] = $data['status'] ?? EventAttendee::STATUS_INVITED;

        $attendee = $this->model->create($data);

        return (new EventAttendeeResource($attendee))->response()->setStatusCode(201);
    }

    public function respond(int $id): JsonResponse
    {
        $attendee = $this->model->with('event')->findOrFail($id);

        $data = request()->validate([
            'status' => ['required', Rule::in([
                EventAttendee::STATUS_ACCEPTED,
                EventAttendee::STATUS_DECLINED,
                EventAttendee::STATUS_TENTATIVE,
            ])],
        ]);

        if ($attendee->event?->status === Event::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'This event has been cancelled.',
            ], 422);
        }

        $attendee->update(['status' => $data['status']]);

        return (new EventAttendeeResource($attendee->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'event_id', 'status'];
    }
}
