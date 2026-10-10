<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\RejectTimesheetRequest;
use App\Http\Requests\Api\V1\StoreTimesheetRequest;
use App\Http\Requests\Api\V1\UpdateTimesheetRequest;
use App\Http\Resources\TimesheetResource;
use App\Models\Timesheet;
use Illuminate\Http\JsonResponse;

class TimesheetController extends BaseController
{
    /** @var Timesheet */
    protected $model;

    protected string $resourceClass = TimesheetResource::class;

    protected string $storeRequestClass = StoreTimesheetRequest::class;

    protected string $updateRequestClass = UpdateTimesheetRequest::class;

    public function __construct()
    {
        $this->model = new Timesheet;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->withCount('entries');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return TimesheetResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Timesheet::STATUS_DRAFT;

        $timesheet = $this->model->create($data);

        return (new TimesheetResource($timesheet))->response()->setStatusCode(201);
    }

    /**
     * Move a draft (or a bounced-back) timesheet into review.
     */
    public function submit(int $id): JsonResponse
    {
        $timesheet = $this->model->findOrFail($id);

        if (! in_array($timesheet->status, [Timesheet::STATUS_DRAFT, Timesheet::STATUS_REJECTED], true)) {
            return response()->json([
                'message' => 'Only a draft or rejected timesheet can be submitted.',
            ], 422);
        }

        $timesheet->update([
            'status' => Timesheet::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return (new TimesheetResource($timesheet->fresh()))->response();
    }

    /**
     * Sign off a submitted timesheet only; drafts never skip review.
     */
    public function approve(int $id): JsonResponse
    {
        $timesheet = $this->model->findOrFail($id);

        if ($timesheet->status !== Timesheet::STATUS_SUBMITTED) {
            return response()->json([
                'message' => 'Only a submitted timesheet can be approved.',
            ], 422);
        }

        $timesheet->update([
            'status' => Timesheet::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        return (new TimesheetResource($timesheet->fresh()))->response();
    }

    /**
     * Bounce a submitted timesheet back with the reviewer's reason.
     */
    public function reject(int $id): JsonResponse
    {
        $request = app(RejectTimesheetRequest::class);

        $timesheet = $this->model->findOrFail($id);

        if ($timesheet->status !== Timesheet::STATUS_SUBMITTED) {
            return response()->json([
                'message' => 'Only a submitted timesheet can be rejected.',
            ], 422);
        }

        $timesheet->update([
            'status' => Timesheet::STATUS_REJECTED,
            'rejected_reason' => $request->validated()['rejected_reason'],
        ]);

        return (new TimesheetResource($timesheet->fresh()))->response();
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
        return ['title'];
    }
}
