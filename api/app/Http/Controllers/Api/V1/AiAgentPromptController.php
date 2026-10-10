<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAiAgentPromptRequest;
use App\Http\Requests\Api\V1\UpdateAiAgentPromptRequest;
use App\Http\Resources\AiAgentPromptResource;
use App\Models\AiAgentPrompt;
use Illuminate\Http\JsonResponse;

class AiAgentPromptController extends BaseController
{
    /** @var AiAgentPrompt */
    protected $model;

    protected string $resourceClass = AiAgentPromptResource::class;

    protected string $storeRequestClass = StoreAiAgentPromptRequest::class;

    protected string $updateRequestClass = UpdateAiAgentPromptRequest::class;

    public function __construct()
    {
        $this->model = new AiAgentPrompt;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('label')->paginate(min($perPage, 100));

        return AiAgentPromptResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['is_shared'] = $data['is_shared'] ?? false;

        $prompt = $this->model->create($data);

        return (new AiAgentPromptResource($prompt))->response()->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'category', 'is_shared'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['label'];
    }
}
