<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreYoutubePlaylistRequest;
use App\Http\Requests\Api\V1\UpdateYoutubePlaylistRequest;
use App\Http\Resources\YoutubePlaylistResource;
use App\Models\YoutubePlaylist;
use Illuminate\Http\JsonResponse;

class YoutubePlaylistController extends BaseController
{
    /** @var YoutubePlaylist */
    protected $model;

    protected string $resourceClass = YoutubePlaylistResource::class;

    protected string $storeRequestClass = StoreYoutubePlaylistRequest::class;

    protected string $updateRequestClass = UpdateYoutubePlaylistRequest::class;

    public function __construct()
    {
        $this->model = new YoutubePlaylist;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return YoutubePlaylistResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'visibility'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
