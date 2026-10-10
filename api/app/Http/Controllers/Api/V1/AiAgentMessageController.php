<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SendAiAgentMessageRequest;
use App\Http\Requests\Api\V1\UpdateAiAgentMessageRequest;
use App\Http\Resources\AiAgentMessageResource;
use App\Models\AiAgentMessage;
use App\Models\AiAgentThread;
use App\Services\AiAgentService;
use Illuminate\Http\JsonResponse;

class AiAgentMessageController extends BaseController
{
    /** @var AiAgentMessage */
    protected $model;

    protected string $resourceClass = AiAgentMessageResource::class;

    protected string $storeRequestClass = SendAiAgentMessageRequest::class;

    protected string $updateRequestClass = UpdateAiAgentMessageRequest::class;

    public function __construct()
    {
        $this->model = new AiAgentMessage;
    }

    /**
     * The conversation transcript for a thread, oldest message first.
     */
    public function index(?AiAgentThread $aiAgentThread = null): JsonResponse
    {
        $thread = $aiAgentThread ?? abort(404);

        $perPage = request()->integer('per_page', 15);
        $items = $thread->messages()
            ->orderBy('created_at')
            ->paginate(min($perPage, 100));

        return AiAgentMessageResource::collection($items)->response();
    }

    /**
     * A user message is stored, handed to the configured driver, and the
     * assistant's reply is stored alongside it before the thread is stamped.
     */
    public function store(?AiAgentThread $aiAgentThread = null): JsonResponse
    {
        $thread = $aiAgentThread ?? abort(404);

        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $userMessage = $thread->messages()->create([
            'company_id' => $thread->company_id,
            'role' => AiAgentMessage::ROLE_USER,
            'body' => $data['body'],
        ]);

        $reply = app(AiAgentService::class)->reply($thread, $data['body']);

        $assistantMessage = $thread->messages()->create([
            'company_id' => $thread->company_id,
            'role' => AiAgentMessage::ROLE_ASSISTANT,
            'body' => $reply,
        ]);

        $thread->update(['last_message_at' => now()]);

        return AiAgentMessageResource::collection([$userMessage, $assistantMessage])
            ->response()
            ->setStatusCode(201);
    }
}
