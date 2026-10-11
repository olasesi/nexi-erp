<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreKbArticleRequest;
use App\Http\Requests\Api\V1\UpdateKbArticleRequest;
use App\Http\Resources\KbArticleResource;
use App\Models\KbArticle;
use Illuminate\Http\JsonResponse;

class KbArticleController extends BaseController
{
    /** @var KbArticle */
    protected $model;

    protected string $resourceClass = KbArticleResource::class;

    protected string $storeRequestClass = StoreKbArticleRequest::class;

    protected string $updateRequestClass = UpdateKbArticleRequest::class;

    public function __construct()
    {
        $this->model = new KbArticle;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('category');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return KbArticleResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? KbArticle::STATUS_DRAFT;

        $article = $this->model->create($data);

        return (new KbArticleResource($article->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $article = $this->model->with('category')->findOrFail($id);

        $article->increment('views');

        return (new KbArticleResource($article->fresh('category')))->response();
    }

    public function publish(int $id): JsonResponse
    {
        $article = $this->model->findOrFail($id);

        if ($article->status !== KbArticle::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft article can be published.',
            ], 422);
        }

        $article->update([
            'status' => KbArticle::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return (new KbArticleResource($article->fresh('category')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'kb_category_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'body'];
    }
}
