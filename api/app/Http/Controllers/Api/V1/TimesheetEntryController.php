<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTimesheetEntryRequest;
use App\Http\Requests\Api\V1\UpdateTimesheetEntryRequest;
use App\Http\Resources\TimesheetEntryResource;
use App\Models\TimesheetEntry;
use Illuminate\Http\JsonResponse;

class TimesheetEntryController extends BaseController
{
    /** @var TimesheetEntry */
    protected $model;

    protected string $resourceClass = TimesheetEntryResource::class;

    protected string $storeRequestClass = StoreTimesheetEntryRequest::class;

    protected string $updateRequestClass = UpdateTimesheetEntryRequest::class;

    public function __construct()
    {
        $this->model = new TimesheetEntry;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('timesheet');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('work_date')->orderByDesc('id')->paginate(min($perPage, 100));

        return TimesheetEntryResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $entry = $this->model->create($data);

        $entry->timesheet?->recalculateTotal();

        return (new TimesheetEntryResource($entry->fresh('timesheet')))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $entry = $this->model->findOrFail($id);
        $entry->update($request->validated());

        $entry->timesheet?->recalculateTotal();

        return (new TimesheetEntryResource($entry->fresh('timesheet')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'timesheet_id', 'work_date', 'project'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['task', 'notes'];
    }
}
