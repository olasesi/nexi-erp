<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDocumentRequest;
use App\Http\Requests\Api\V1\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class DocumentController extends BaseController
{
    /** @var Document */
    protected $model;

    protected string $resourceClass = DocumentResource::class;

    protected string $storeRequestClass = StoreDocumentRequest::class;

    protected string $updateRequestClass = UpdateDocumentRequest::class;

    public function __construct()
    {
        $this->model = new Document;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return DocumentResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['type'] = $data['type'] ?? Document::TYPE_FILE;
        $data['version'] = $data['version'] ?? 1;
        $data['size'] = $data['size'] ?? 0;

        $document = $this->model->create($data);

        return (new DocumentResource($document->fresh()))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $data = $request->validated();
        $document = $this->model->findOrFail($id);

        if (array_key_exists('content', $data) || array_key_exists('url', $data)) {
            $data['version'] = (int) $document->version + 1;
        }

        $document->update($data);

        return (new DocumentResource($document->fresh()))->response();
    }

    public function share(int $id): JsonResponse
    {
        $document = $this->model->findOrFail($id);

        $data = request()->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $existing = $document->shared_with ?? [];

        $document->shared_with = array_values(array_unique([
            ...$existing,
            ...array_map('intval', Arr::wrap($data['user_ids'])),
        ]));
        $document->save();

        return (new DocumentResource($document->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'folder_id', 'type'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
