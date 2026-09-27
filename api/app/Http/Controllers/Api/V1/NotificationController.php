<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNotificationRequest;
use App\Http\Requests\Api\V1\UpdateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;

class NotificationController extends BaseController
{
    /** @var Notification */
    protected $model;

    protected string $resourceClass = NotificationResource::class;

    protected string $storeRequestClass = StoreNotificationRequest::class;

    protected string $updateRequestClass = UpdateNotificationRequest::class;

    public function __construct()
    {
        $this->model = new Notification;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->latest();

        if ($user = auth('api')->user()) {
            $query->where(fn ($q) => $q->where('company_id', $user->company_id)
                ->where(fn ($inner) => $inner->whereNull('user_id')->orWhere('user_id', $user->id)));
        }

        $query = $this->applyFilters($query);

        if (request()->boolean('unread')) {
            $query->unread();
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return NotificationResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        if (array_key_exists('payload', $data)) {
            $data['data'] = $data['payload'];
            unset($data['payload']);
        }

        $item = $this->model->create($data);

        app(MetricsService::class)->recordNotification();

        return (new NotificationResource($item))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('is_read', $data)) {
            $data['read_at'] = $data['is_read'] ? now() : null;
            unset($data['is_read']);
        }

        if (array_key_exists('payload', $data)) {
            $data['data'] = $data['payload'];
            unset($data['payload']);
        }

        $item->update($data);

        return (new NotificationResource($item->fresh()))->response();
    }

    public function unreadCount(): JsonResponse
    {
        $user = auth('api')->user();

        $count = $this->model->query()
            ->where('company_id', $user?->company_id)
            ->visibleTo($user->id ?? 0)
            ->unread()
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markAllRead(): JsonResponse
    {
        $user = auth('api')->user();

        $count = $this->model->query()
            ->where('company_id', $user?->company_id)
            ->visibleTo($user->id ?? 0)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['updated' => $count]);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['type', 'user_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'body'];
    }
}
