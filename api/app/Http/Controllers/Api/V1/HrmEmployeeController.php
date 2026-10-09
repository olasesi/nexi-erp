<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHrmEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateHrmEmployeeRequest;
use App\Http\Resources\HrmEmployeeResource;
use App\Models\HrmAttendance;
use App\Models\HrmEmployee;
use App\Models\HrmLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrmEmployeeController extends BaseController
{
    /** @var HrmEmployee */
    protected $model;

    protected string $resourceClass = HrmEmployeeResource::class;

    protected string $storeRequestClass = StoreHrmEmployeeRequest::class;

    protected string $updateRequestClass = UpdateHrmEmployeeRequest::class;

    /** Relations carried on every single-employee payload. */
    /** @var list<string> */
    protected array $includes = ['department', 'designation', 'manager'];

    public function __construct()
    {
        $this->model = new HrmEmployee;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with($this->includes);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('employee_code')->paginate(min($perPage, 100));

        return HrmEmployeeResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->with($this->includes)->findOrFail($id);

        return (new HrmEmployeeResource($item))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $item->update($request->validated());

        return (new HrmEmployeeResource($item->fresh($this->includes)))->response();
    }

    /**
     * The HRM dashboard tile: headcount, today's attendance roll call and the
     * leave pipeline in one payload, shaped so the SPA can render cards
     * without further round trips.
     */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $companyId = $data['company_id'] ?? auth('api')->user()?->company_id;

        if ($companyId === null) {
            return response()->json([
                'message' => 'A company_id is required for the HRM summary.',
            ], 422);
        }

        $companyId = (int) $companyId;
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $employees = DB::table('hrm_employees')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        $total = (clone $employees)->count();
        $working = (clone $employees)->whereIn('employment_status', HrmEmployee::ACTIVE_STATUSES)->count();

        $byStatus = [];

        foreach ((clone $employees)->selectRaw('employment_status, count(*) as aggregate')
            ->groupBy('employment_status')
            ->get() as $row) {
            $byStatus[(string) $row->employment_status] = (int) $row->aggregate;
        }

        $departmentNames = DB::table('hrm_departments')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->pluck('name', 'id');

        $byDepartment = (clone $employees)
            ->selectRaw('department_id, count(*) as aggregate')
            ->groupBy('department_id')
            ->get()
            ->map(fn ($row) => [
                'department_id' => $row->department_id === null ? null : (int) $row->department_id,
                'name' => isset($departmentNames[$row->department_id])
                    ? (string) $departmentNames[$row->department_id]
                    : 'Unassigned',
                'count' => (int) $row->aggregate,
            ])
            ->sortBy('count', SORT_REGULAR, true)
            ->values()
            ->all();

        $attendance = array_fill_keys(HrmAttendance::STATUSES, 0);

        foreach (DB::table('hrm_attendance')
            ->where('company_id', $companyId)
            ->whereDate('attendance_date', $today)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->get() as $row) {
            $attendance[(string) $row->status] = (int) $row->aggregate;
        }

        $recorded = array_sum($attendance);
        $attendance['missing'] = max(0, $working - $recorded);

        $leaves = DB::table('hrm_leaves')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        $recentEmployees = $this->model->with($this->includes)
            ->where('company_id', $companyId)
            ->latest('id')
            ->limit(5)
            ->get();

        return response()->json([
            'company_id' => $companyId,
            'as_of' => $today->toDateString(),
            'total_employees' => $total,
            'working_employees' => $working,
            'by_status' => $byStatus,
            'by_department' => $byDepartment,
            'attendance_today' => $attendance,
            'attendance_rate' => $working === 0
                ? 0.0
                : round(min($recorded, $working) / $working, 2),
            'on_leave_today' => (clone $leaves)
                ->where('status', HrmLeave::STATUS_APPROVED)
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->count(),
            'pending_leaves' => (clone $leaves)->where('status', HrmLeave::STATUS_PENDING)->count(),
            'approved_leaves_this_month' => (clone $leaves)
                ->where('status', HrmLeave::STATUS_APPROVED)
                ->whereBetween('start_date', [$monthStart, $monthEnd])
                ->count(),
            'data' => [
                'recent_employees' => HrmEmployeeResource::collection($recentEmployees)->resolve(),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'department_id', 'designation_id', 'employment_status', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['employee_code', 'first_name', 'last_name', 'email', 'phone'];
    }
}
