<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTicketRequest;
use App\Http\Requests\Api\V1\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class TicketController extends BaseController
{
    /** @var Ticket */
    protected $model;

    protected string $resourceClass = TicketResource::class;

    protected string $storeRequestClass = StoreTicketRequest::class;

    protected string $updateRequestClass = UpdateTicketRequest::class;

    public function __construct()
    {
        $this->model = new Ticket;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->withCount('replies');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return TicketResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $ticket = $this->model->withCount('replies')->findOrFail($id);

        return (new TicketResource($ticket))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Ticket::STATUS_OPEN;
        $data['priority'] = $data['priority'] ?? Ticket::PRIORITY_MEDIUM;

        $ticket = $this->model->create($data);

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    public function assign(int $id): JsonResponse
    {
        $ticket = $this->model->findOrFail($id);

        if ($ticket->status === Ticket::STATUS_CLOSED) {
            return response()->json([
                'message' => 'A closed ticket cannot be assigned.',
            ], 422);
        }

        $data = request()->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket->update(['assigned_to' => $data['assigned_to']]);

        return (new TicketResource($ticket->fresh()))->response();
    }

    public function resolve(int $id): JsonResponse
    {
        $ticket = $this->model->findOrFail($id);

        if (! in_array($ticket->status, [Ticket::STATUS_OPEN, Ticket::STATUS_PENDING], true)) {
            return response()->json([
                'message' => 'Only an open or pending ticket can be resolved.',
            ], 422);
        }

        $ticket->update([
            'status' => Ticket::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        return (new TicketResource($ticket->fresh()))->response();
    }

    public function close(int $id): JsonResponse
    {
        $ticket = $this->model->findOrFail($id);

        if ($ticket->status === Ticket::STATUS_CLOSED) {
            return response()->json([
                'message' => 'This ticket is already closed.',
            ], 422);
        }

        $ticket->update([
            'status' => Ticket::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return (new TicketResource($ticket->fresh()))->response();
    }

    public function reopen(int $id): JsonResponse
    {
        $ticket = $this->model->findOrFail($id);

        if (! in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true)) {
            return response()->json([
                'message' => 'Only a resolved or closed ticket can be reopened.',
            ], 422);
        }

        $ticket->update([
            'status' => Ticket::STATUS_OPEN,
            'resolved_at' => null,
            'closed_at' => null,
        ]);

        return (new TicketResource($ticket->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'priority', 'assigned_to', 'contact_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['subject', 'ticket_number'];
    }
}
