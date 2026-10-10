<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMessageRequest;
use App\Http\Requests\Api\V1\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

class MessageController extends BaseController
{
    /** @var Message */
    protected $model;

    protected string $resourceClass = MessageResource::class;

    protected string $storeRequestClass = StoreMessageRequest::class;

    protected string $updateRequestClass = UpdateMessageRequest::class;

    public function __construct()
    {
        $this->model = new Message;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['user', 'contact']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('created_at')->paginate(min($perPage, 100));

        return MessageResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $conversation = Conversation::findOrFail((int) $data['conversation_id']);

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['direction'] = $data['direction'] ?? Message::DIRECTION_OUT;
        $data['read_at'] = $data['direction'] === Message::DIRECTION_IN ? null : now();

        $message = $this->model->create($data);

        $conversation->update(['last_message_at' => now()]);

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'conversation_id', 'user_id', 'direction'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['body'];
    }
}
