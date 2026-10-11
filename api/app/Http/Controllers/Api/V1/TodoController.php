<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTodoRequest;
use App\Http\Requests\Api\V1\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use Illuminate\Http\JsonResponse;

class TodoController extends BaseController
{
    /** @var Todo */
    protected $model;

    protected string $resourceClass = TodoResource::class;

    protected string $storeRequestClass = StoreTodoRequest::class;

    protected string $updateRequestClass = UpdateTodoRequest::class;

    public function __construct()
    {
        $this->model = new Todo;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return TodoResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Todo::STATUS_PENDING;
        $data['priority'] = $data['priority'] ?? Todo::PRIORITY_MEDIUM;

        $todo = $this->model->create($data);

        return (new TodoResource($todo))->response()->setStatusCode(201);
    }

    public function complete(int $id): JsonResponse
    {
        $todo = $this->model->findOrFail($id);

        if (! in_array($todo->status, [Todo::STATUS_PENDING, Todo::STATUS_IN_PROGRESS], true)) {
            return response()->json([
                'message' => 'Only an open to-do can be completed.',
            ], 422);
        }

        $todo->update([
            'status' => Todo::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return (new TodoResource($todo->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'priority', 'user_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'description'];
    }
}
