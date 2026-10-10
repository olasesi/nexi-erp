<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\JobOpeningResource;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;

class JobOpeningController extends BaseController
{
    public function index(): JsonResponse
    {
        $query = JobPosting::query()->where('status', JobPosting::STATUS_PUBLISHED);

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return JobOpeningResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $posting = JobPosting::query()
            ->where('status', JobPosting::STATUS_PUBLISHED)
            ->findOrFail($id);

        return (new JobOpeningResource($posting))->response();
    }
}
