<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHrmAttendanceRequest;
use App\Http\Requests\Api\V1\UpdateHrmAttendanceRequest;
use App\Http\Resources\HrmAttendanceResource;
use App\Models\HrmAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class HrmAttendanceController extends BaseController
{
    /** @var HrmAttendance */
    protected $model;

    protected string $resourceClass = HrmAttendanceResource::class;

    protected string $storeRequestClass = StoreHrmAttendanceRequest::class;

    protected string $updateRequestClass = UpdateHrmAttendanceRequest::class;

    public function __construct()
    {
        $this->model = new HrmAttendance;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['employee', 'createdBy']);
        $query = $this->applyFilters($query);

        if ($date = request('attendance_date')) {
            $query->whereDate('attendance_date', $date);
        }

        if ($from = request('from_date')) {
            $query->whereDate('attendance_date', '>=', $from);
        }

        if ($to = request('to_date')) {
            $query->whereDate('attendance_date', '<=', $to);
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('attendance_date')->orderBy('employee_id')->paginate(min($perPage, 100));

        return HrmAttendanceResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? HrmAttendance::STATUS_PRESENT;
        $data = $this->withWorkHours($data);

        $item = $this->model->with('employee')->create($data);

        return (new HrmAttendanceResource($item))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->with(['employee', 'createdBy'])->findOrFail($id);

        return (new HrmAttendanceResource($item))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $data = $request->validated();

        // Recompute only when the punch times actually move; an edit that
        // touches nothing but the status keeps the hours already recorded.
        if (($data['work_hours'] ?? null) === null
            && (($data['check_in'] ?? null) !== null || ($data['check_out'] ?? null) !== null)) {
            $data = $this->withWorkHours(array_merge([
                'check_in' => $item->check_in,
                'check_out' => $item->check_out,
            ], $data));
        }

        $item->update($data);

        return (new HrmAttendanceResource($item->fresh(['employee', 'createdBy'])))->response();
    }

    /**
     * Hours worked derived from the punch times when the caller did not send
     * an explicit total. An overnight shift (checkout before checkin) rolls
     * over midnight instead of producing a negative number.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withWorkHours(array $data): array
    {
        if (isset($data['work_hours'])
            || ($data['check_in'] ?? null) === null
            || ($data['check_out'] ?? null) === null) {
            return $data;
        }

        $checkIn = Carbon::parse((string) $data['check_in']);
        $checkOut = Carbon::parse((string) $data['check_out']);

        if ($checkOut->lessThan($checkIn)) {
            $checkOut->addDay();
        }

        $data['work_hours'] = round($checkIn->diffInMinutes($checkOut) / 60, 2);

        return $data;
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'employee_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['notes'];
    }
}
