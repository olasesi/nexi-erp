<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNoteRequest;
use App\Http\Requests\Api\V1\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;

class NoteController extends BaseController
{
    /** @var Note */
    protected $model;

    protected string $resourceClass = NoteResource::class;

    protected string $storeRequestClass = StoreNoteRequest::class;

    protected string $updateRequestClass = UpdateNoteRequest::class;

    public function __construct()
    {
        $this->model = new Note;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(min($perPage, 100));

        return NoteResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['is_pinned'] = $data['is_pinned'] ?? false;

        $note = $this->model->create($data);

        return (new NoteResource($note))->response()->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'user_id', 'is_pinned'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'body'];
    }
}
