<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreYoutubeActivityRequest;
use App\Http\Requests\Api\V1\UpdateYoutubeActivityRequest;
use App\Http\Resources\YoutubeActivityResource;
use App\Models\YoutubeActivity;
use Illuminate\Http\JsonResponse;

class YoutubeActivityController extends BaseController
{
    /** @var YoutubeActivity */
    protected $model;

    protected string $resourceClass = YoutubeActivityResource::class;

    protected string $storeRequestClass = StoreYoutubeActivityRequest::class;

    protected string $updateRequestClass = UpdateYoutubeActivityRequest::class;

    public function __construct()
    {
        $this->model = new YoutubeActivity;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('occurred_at')->paginate(min($perPage, 100));

        return YoutubeActivityResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'type', 'youtube_video_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['description'];
    }
}
