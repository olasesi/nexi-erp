<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSmartReportRequest;
use App\Http\Requests\Api\V1\UpdateSmartReportRequest;
use App\Http\Resources\SmartReportResource;
use App\Models\SmartReport;
use App\Services\SmartReportRunner;
use Illuminate\Http\JsonResponse;

class SmartReportController extends BaseController
{
    /** @var SmartReport */
    protected $model;

    protected string $resourceClass = SmartReportResource::class;

    protected string $storeRequestClass = StoreSmartReportRequest::class;

    protected string $updateRequestClass = UpdateSmartReportRequest::class;

    public function __construct(private readonly SmartReportRunner $runner)
    {
        $this->model = new SmartReport;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return SmartReportResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['chart_type'] = $data['chart_type'] ?? 'table';
        $data['is_shared'] = $data['is_shared'] ?? false;

        $item = $this->model->create($data);

        return (new SmartReportResource($item))->response()->setStatusCode(201);
    }

    /**
     * Execute a saved report against its live source.
     */
    public function run(int $id): JsonResponse
    {
        $report = $this->model->findOrFail($id);

        $data = request()->validate([
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        return response()->json(
            $this->runner->run($report, (int) ($data['limit'] ?? SmartReportRunner::MAX_ROWS))
        );
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'source', 'is_shared'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
