<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSuggestionRequest;
use App\Http\Requests\Api\V1\UpdateSuggestionRequest;
use App\Http\Resources\SuggestionResource;
use App\Models\Suggestion;
use Illuminate\Http\JsonResponse;

class SuggestionController extends BaseController
{
    /** @var Suggestion */
    protected $model;

    protected string $resourceClass = SuggestionResource::class;

    protected string $storeRequestClass = StoreSuggestionRequest::class;

    protected string $updateRequestClass = UpdateSuggestionRequest::class;

    public function __construct()
    {
        $this->model = new Suggestion;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->latest('id')->paginate(min($perPage, 100));

        return SuggestionResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['submitted_by'] = $data['submitted_by'] ?? $user->id;
        }

        $data = $this->stampReview($data, Suggestion::OPEN);
        $data['status'] = $data['status'] ?? Suggestion::OPEN;

        $item = $this->model->create($data);

        return (new SuggestionResource($item))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $data = $request->validated();

        $data = $this->stampReview($data, $item->status);

        $item->update($data);

        return (new SuggestionResource($item->fresh()))->response();
    }

    /**
     * Any status move away from the previous one is a review: record who did
     * it and when, so the box keeps an auditable trail without a workflow
     * engine.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampReview(array $data, string $currentStatus): array
    {
        $status = $data['status'] ?? null;

        if ($status !== null && $status !== $currentStatus && ($user = auth('api')->user())) {
            $data['reviewed_by'] = $user->id;
            $data['reviewed_at'] = now();
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'category'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'body'];
    }
}
