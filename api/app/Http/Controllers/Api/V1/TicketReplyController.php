<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTicketReplyRequest;
use App\Http\Requests\Api\V1\UpdateTicketReplyRequest;
use App\Http\Resources\TicketReplyResource;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\JsonResponse;

class TicketReplyController extends BaseController
{
    /** @var TicketReply */
    protected $model;

    protected string $resourceClass = TicketReplyResource::class;

    protected string $storeRequestClass = StoreTicketReplyRequest::class;

    protected string $updateRequestClass = UpdateTicketReplyRequest::class;

    public function __construct()
    {
        $this->model = new TicketReply;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['user', 'contact']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('created_at')->paginate(min($perPage, 100));

        return TicketReplyResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $reply = $this->model->with(['user', 'contact'])->findOrFail($id);

        return (new TicketReplyResource($reply))->response();
    }

    /**
     * A conversation stops once the ticket is closed: reopening is the only
     * way to invite further replies.
     */
    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $ticket = Ticket::findOrFail((int) $data['ticket_id']);

        if ($ticket->status === Ticket::STATUS_CLOSED) {
            return response()->json([
                'message' => 'Replies cannot be added to a closed ticket.',
            ], 422);
        }

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['user_id'] = $data['user_id'] ?? $user->id;
        }

        $data['is_internal'] = $data['is_internal'] ?? false;

        $reply = $this->model->create($data);

        return (new TicketReplyResource($reply))->response()->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'ticket_id', 'user_id', 'is_internal'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['body'];
    }
}
