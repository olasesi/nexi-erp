<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAiImageRequest;
use App\Http\Requests\Api\V1\UpdateAiImageRequest;
use App\Http\Resources\AiImageResource;
use App\Models\AiImage;
use App\Services\AiImageService;
use Illuminate\Http\JsonResponse;

class AiImageController extends BaseController
{
    /** @var AiImage */
    protected $model;

    protected string $resourceClass = AiImageResource::class;

    protected string $storeRequestClass = StoreAiImageRequest::class;

    protected string $updateRequestClass = UpdateAiImageRequest::class;

    public function __construct()
    {
        $this->model = new AiImage;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query
            ->orderByDesc('created_at')
            ->paginate(min($perPage, 100));

        return AiImageResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['user_id'] = $data['user_id'] ?? $user->id;
        }

        $data['size'] = $data['size'] ?? '512x512';
        $data['status'] = $data['status'] ?? AiImage::STATUS_PENDING;

        $image = $this->model->create($data);

        return (new AiImageResource($image))->response()->setStatusCode(201);
    }

    /**
     * Render the image through the configured driver.
     */
    public function generate(int $id): JsonResponse
    {
        $image = $this->model->findOrFail($id);

        app(AiImageService::class)->generate($image);

        return (new AiImageResource($image->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'user_id', 'style'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['prompt'];
    }
}
