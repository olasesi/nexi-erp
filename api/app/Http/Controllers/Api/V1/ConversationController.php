<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreConversationRequest;
use App\Http\Requests\Api\V1\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationController extends BaseController
{
    /** @var Conversation */
    protected $model;

    protected string $resourceClass = ConversationResource::class;

    protected string $storeRequestClass = StoreConversationRequest::class;

    protected string $updateRequestClass = UpdateConversationRequest::class;

    public function __construct()
    {
        $this->model = new Conversation;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()
            ->with(['contact', 'assignee'])
            ->withCount('messages');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(min($perPage, 100));

        return ConversationResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $conversation = $this->model
            ->with(['contact', 'assignee'])
            ->withCount('messages')
            ->findOrFail($id);

        return (new ConversationResource($conversation))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Conversation::STATUS_OPEN;
        $data['is_group'] = $data['is_group'] ?? false;

        $conversation = $this->model->create($data);

        return (new ConversationResource($conversation))->response()->setStatusCode(201);
    }

    public function close(int $id): JsonResponse
    {
        $conversation = $this->model->findOrFail($id);

        if (in_array($conversation->status, [Conversation::STATUS_CLOSED, Conversation::STATUS_ARCHIVED], true)) {
            return response()->json([
                'message' => 'This conversation is already closed or archived.',
            ], 422);
        }

        $conversation->update(['status' => Conversation::STATUS_CLOSED]);

        return (new ConversationResource($conversation->fresh()))->response();
    }

    public function reopen(int $id): JsonResponse
    {
        $conversation = $this->model->findOrFail($id);

        if (in_array($conversation->status, [Conversation::STATUS_ARCHIVED, Conversation::STATUS_OPEN], true)) {
            return response()->json([
                'message' => 'This conversation cannot be reopened.',
            ], 422);
        }

        $conversation->update(['status' => Conversation::STATUS_OPEN]);

        return (new ConversationResource($conversation->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'channel', 'status', 'contact_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['subject'];
    }
}
