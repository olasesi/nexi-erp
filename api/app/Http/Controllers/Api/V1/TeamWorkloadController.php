<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use App\Models\Ticket;
use App\Models\Timesheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Read-only roll-up of each employee's active work: outstanding project
 * tasks, open support tickets and timesheets awaiting a decision. Everything
 * is derived on the fly from existing tables, so the add-on carries no schema
 * of its own.
 */
class TeamWorkloadController extends Controller
{
    /** Ticket statuses that still count as outstanding work. */
    /** @var list<string> */
    private const OPEN_TICKET_STATUSES = [
        Ticket::STATUS_OPEN,
        Ticket::STATUS_PENDING,
    ];

    /** Timesheets awaiting or holding a decision (pending or approved). */
    /** @var list<string> */
    private const PENDING_TIMESHEET_STATUSES = [
        Timesheet::STATUS_SUBMITTED,
        Timesheet::STATUS_APPROVED,
    ];

    /**
     * One row per employee with their current workload and a combined total.
     */
    public function index(): JsonResponse
    {
        $companyId = auth('api')->user()?->company_id;

        if ($companyId === null) {
            return response()->json([
                'message' => 'A company is required for the team workload.',
            ], 422);
        }

        return response()->json($this->workload((int) $companyId));
    }

    /**
     * Company-wide totals across every employee's workload.
     */
    public function summary(): JsonResponse
    {
        $companyId = auth('api')->user()?->company_id;

        if ($companyId === null) {
            return response()->json([
                'message' => 'A company is required for the team workload.',
            ], 422);
        }

        $rows = $this->workload((int) $companyId);

        $totalTasks = 0;
        $totalTickets = 0;
        $totalTimesheets = 0;

        foreach ($rows as $row) {
            $totalTasks += $row['open_tasks'];
            $totalTickets += $row['open_tickets'];
            $totalTimesheets += $row['pending_timesheets'];
        }

        $totalEmployees = count($rows);
        $totalLoad = $totalTasks + $totalTickets + $totalTimesheets;

        return response()->json([
            'total_employees' => $totalEmployees,
            'total_open_tasks' => $totalTasks,
            'total_open_tickets' => $totalTickets,
            'total_pending_timesheets' => $totalTimesheets,
            'average_load' => $totalEmployees === 0
                ? 0.0
                : round($totalLoad / $totalEmployees, 2),
        ]);
    }

    /**
     * Build the per-employee workload rows, scoped to a single company.
     *
     * @return array<int, array{
     *     employee_id: int,
     *     employee_name: string|null,
     *     open_tasks: int,
     *     open_tickets: int,
     *     pending_timesheets: int,
     *     total_load: int
     * }>
     */
    private function workload(int $companyId): array
    {
        $tasksByUser = $this->countsBy(
            'project_tasks',
            'assigned_to',
            $companyId,
            ProjectTask::OPEN_STATUSES,
        );

        $ticketsByUser = $this->countsBy(
            'tickets',
            'assigned_to',
            $companyId,
            self::OPEN_TICKET_STATUSES,
        );

        $timesheetsByEmployee = $this->countsBy(
            'timesheets',
            'employee_id',
            $companyId,
            self::PENDING_TIMESHEET_STATUSES,
        );

        $employees = DB::table('hrm_employees')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'user_id']);

        return $employees->map(function (object $employee) use (
            $tasksByUser,
            $ticketsByUser,
            $timesheetsByEmployee,
        ): array {
            $employeeId = (int) $employee->id;
            $userId = (int) ($employee->user_id ?? 0);

            $openTasks = $tasksByUser[$userId] ?? 0;
            $openTickets = $ticketsByUser[$userId] ?? 0;
            $pendingTimesheets = $timesheetsByEmployee[$employeeId] ?? 0;

            $name = trim(((string) $employee->first_name).' '.((string) ($employee->last_name ?? '')));

            return [
                'employee_id' => $employeeId,
                'employee_name' => $name === '' ? null : $name,
                'open_tasks' => $openTasks,
                'open_tickets' => $openTickets,
                'pending_timesheets' => $pendingTimesheets,
                'total_load' => $openTasks + $openTickets + $pendingTimesheets,
            ];
        })->values()->all();
    }

    /**
     * Count rows per owner column for a company, limited to the given statuses.
     *
     * @param  list<string>  $statuses
     * @return array<int, int>
     */
    private function countsBy(string $table, string $column, int $companyId, array $statuses): array
    {
        $rows = DB::table($table)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotNull($column)
            ->whereIn('status', $statuses)
            ->select($column, DB::raw('count(*) as aggregate'))
            ->groupBy($column)
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->{$column}] = (int) $row->aggregate;
        }

        return $counts;
    }
}
