<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreYoutubeChannelRequest;
use App\Http\Requests\Api\V1\UpdateYoutubeChannelRequest;
use App\Http\Resources\YoutubeChannelResource;
use App\Models\YoutubeChannel;
use Illuminate\Http\JsonResponse;

class YoutubeChannelController extends BaseController
{
    /** @var YoutubeChannel */
    protected $model;

    protected string $resourceClass = YoutubeChannelResource::class;

    protected string $storeRequestClass = StoreYoutubeChannelRequest::class;

    protected string $updateRequestClass = UpdateYoutubeChannelRequest::class;

    public function __construct()
    {
        $this->model = new YoutubeChannel;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return YoutubeChannelResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'is_connected'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title'];
    }
}
