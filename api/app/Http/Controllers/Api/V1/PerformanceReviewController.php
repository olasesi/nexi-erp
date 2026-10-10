<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StorePerformanceReviewRequest;
use App\Http\Requests\Api\V1\UpdatePerformanceReviewRequest;
use App\Http\Resources\PerformanceReviewResource;
use App\Models\PerformanceReview;
use Illuminate\Http\JsonResponse;

class PerformanceReviewController extends BaseController
{
    /** @var PerformanceReview */
    protected $model;

    protected string $resourceClass = PerformanceReviewResource::class;

    protected string $storeRequestClass = StorePerformanceReviewRequest::class;

    protected string $updateRequestClass = UpdatePerformanceReviewRequest::class;

    public function __construct()
    {
        $this->model = new PerformanceReview;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with(['employee', 'reviewer']));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return PerformanceReviewResource::collection($items)->response();
    }

    /**
     * A review lands as a draft, and the scores it carries are summarised into
     * the overall score here so no client ever has to do the arithmetic.
     */
    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? PerformanceReview::STATUS_DRAFT;
        $data['overall_score'] = $this->averageOf($data['scores'] ?? null) ?? 0;

        $item = $this->model->create($data);

        return (new PerformanceReviewResource($item))->response()->setStatusCode(201);
    }

    /**
     * Sign off a draft or a submitted review; only reviews that have not been
     * approved yet may take this step.
     */
    public function approve(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if (! in_array($item->status, [PerformanceReview::STATUS_DRAFT, PerformanceReview::STATUS_SUBMITTED], true)) {
            return response()->json([
                'message' => 'Only a draft or submitted review can be approved.',
            ], 422);
        }

        $item->update(['status' => PerformanceReview::STATUS_APPROVED]);

        return (new PerformanceReviewResource($item->fresh()))->response();
    }

    /**
     * Send an approved review back to draft so its scores can be revised.
     */
    public function reopen(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if ($item->status !== PerformanceReview::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Only an approved review can be reopened.',
            ], 422);
        }

        $item->update(['status' => PerformanceReview::STATUS_DRAFT]);

        return (new PerformanceReviewResource($item->fresh()))->response();
    }

    /**
     * Mean of the numeric ratings, rounded to two decimals. Entries that are
     * not numbers (labels, nulls) are ignored rather than failing the review.
     */
    private function averageOf(mixed $scores): ?float
    {
        if (! is_array($scores)) {
            return null;
        }

        /** @var list<float> $values */
        $values = [];

        foreach ($scores as $score) {
            if (is_int($score) || is_float($score) || (is_string($score) && is_numeric($score))) {
                $values[] = (float) $score;
            }
        }

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'employee_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['comments'];
    }
}
