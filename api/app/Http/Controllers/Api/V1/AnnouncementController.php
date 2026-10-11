<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAnnouncementRequest;
use App\Http\Requests\Api\V1\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends BaseController
{
    /** @var Announcement */
    protected $model;

    protected string $resourceClass = AnnouncementResource::class;

    protected string $storeRequestClass = StoreAnnouncementRequest::class;

    protected string $updateRequestClass = UpdateAnnouncementRequest::class;

    public function __construct()
    {
        $this->model = new Announcement;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return AnnouncementResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Announcement::STATUS_DRAFT;
        $data['audience'] = $data['audience'] ?? Announcement::AUDIENCE_ALL;
        $data['priority'] = $data['priority'] ?? Announcement::PRIORITY_NORMAL;

        $announcement = $this->model->create($data);

        return (new AnnouncementResource($announcement))->response()->setStatusCode(201);
    }

    public function publish(int $id): JsonResponse
    {
        $announcement = $this->model->findOrFail($id);

        if ($announcement->status !== Announcement::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft announcement can be published.',
            ], 422);
        }

        $announcement->update([
            'status' => Announcement::STATUS_PUBLISHED,
            'publish_at' => now(),
        ]);

        return (new AnnouncementResource($announcement->fresh()))->response();
    }

    public function archive(int $id): JsonResponse
    {
        $announcement = $this->model->findOrFail($id);

        if ($announcement->status !== Announcement::STATUS_PUBLISHED) {
            return response()->json([
                'message' => 'Only a published announcement can be archived.',
            ], 422);
        }

        $announcement->update(['status' => Announcement::STATUS_ARCHIVED]);

        return (new AnnouncementResource($announcement->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'audience', 'priority'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'body'];
    }
}
