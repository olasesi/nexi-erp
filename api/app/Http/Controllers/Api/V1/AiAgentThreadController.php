<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAiAgentThreadRequest;
use App\Http\Requests\Api\V1\UpdateAiAgentThreadRequest;
use App\Http\Resources\AiAgentThreadResource;
use App\Models\AiAgentThread;
use Illuminate\Http\JsonResponse;

class AiAgentThreadController extends BaseController
{
    /** @var AiAgentThread */
    protected $model;

    protected string $resourceClass = AiAgentThreadResource::class;

    protected string $storeRequestClass = StoreAiAgentThreadRequest::class;

    protected string $updateRequestClass = UpdateAiAgentThreadRequest::class;

    public function __construct()
    {
        $this->model = new AiAgentThread;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->withCount('messages');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(min($perPage, 100));

        return AiAgentThreadResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['user_id'] = $data['user_id'] ?? $user->id;
        }

        $data['title'] = $data['title'] ?? 'New chat';
        $data['status'] = $data['status'] ?? AiAgentThread::STATUS_ACTIVE;

        $thread = $this->model->create($data);

        return (new AiAgentThreadResource($thread))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $thread = $this->model->withCount('messages')->findOrFail($id);

        return (new AiAgentThreadResource($thread))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'user_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
