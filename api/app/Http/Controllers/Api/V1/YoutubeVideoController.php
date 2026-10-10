<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreYoutubeVideoRequest;
use App\Http\Requests\Api\V1\UpdateYoutubeVideoRequest;
use App\Http\Resources\YoutubeVideoResource;
use App\Models\YoutubeVideo;
use Illuminate\Http\JsonResponse;

class YoutubeVideoController extends BaseController
{
    /** @var YoutubeVideo */
    protected $model;

    protected string $resourceClass = YoutubeVideoResource::class;

    protected string $storeRequestClass = StoreYoutubeVideoRequest::class;

    protected string $updateRequestClass = UpdateYoutubeVideoRequest::class;

    public function __construct()
    {
        $this->model = new YoutubeVideo;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return YoutubeVideoResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'youtube_playlist_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
