<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAiDocumentRequest;
use App\Http\Requests\Api\V1\UpdateAiDocumentRequest;
use App\Http\Resources\AiDocumentResource;
use App\Models\AiDocument;
use App\Services\AiDocumentService;
use Illuminate\Http\JsonResponse;

class AiDocumentController extends BaseController
{
    /** @var AiDocument */
    protected $model;

    protected string $resourceClass = AiDocumentResource::class;

    protected string $storeRequestClass = StoreAiDocumentRequest::class;

    protected string $updateRequestClass = UpdateAiDocumentRequest::class;

    public function __construct()
    {
        $this->model = new AiDocument;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query
            ->orderByDesc('created_at')
            ->paginate(min($perPage, 100));

        return AiDocumentResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['user_id'] = $data['user_id'] ?? $user->id;
        }

        $data['language'] = $data['language'] ?? 'en';
        $data['status'] = $data['status'] ?? AiDocument::STATUS_PENDING;

        $document = $this->model->create($data);

        return (new AiDocumentResource($document))->response()->setStatusCode(201);
    }

    /**
     * Draft the document body through the configured driver.
     */
    public function generate(int $id): JsonResponse
    {
        $document = $this->model->findOrFail($id);

        app(AiDocumentService::class)->generate($document);

        return (new AiDocumentResource($document->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'user_id', 'language'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'prompt'];
    }
}
