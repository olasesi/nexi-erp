<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNoticeRequest;
use App\Http\Requests\Api\V1\UpdateNoticeRequest;
use App\Http\Resources\NoticeResource;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;

class NoticeController extends BaseController
{
    /** @var Notice */
    protected $model;

    protected string $resourceClass = NoticeResource::class;

    protected string $storeRequestClass = StoreNoticeRequest::class;

    protected string $updateRequestClass = UpdateNoticeRequest::class;

    public function __construct()
    {
        $this->model = new Notice;
    }

    /**
     * The board shows live notices by default; `?status=all` also returns
     * drafts and expired ones for the management view.
     */
    public function index(): JsonResponse
    {
        $query = $this->model->query();

        if (request()->query('status') !== 'all') {
            $query->active();
        }

        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->latest('published_at')->latest('id')->paginate(min($perPage, 100));

        return NoticeResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        // An undated notice goes live immediately rather than never.
        $data['published_at'] = $data['published_at'] ?? now()->toDateString();

        $item = $this->model->create($data);

        return (new NoticeResource($item))->response()->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'priority'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'body'];
    }
}
