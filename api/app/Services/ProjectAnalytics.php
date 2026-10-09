<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;

/**
 * Project portfolio aggregates behind GET /dashboard/project-summary and the
 * project module's summary feed. Every method runs a fixed number of grouped
 * statements, so the cost does not grow with the number of projects.
 */
class ProjectAnalytics
{
    /**
     * @return array{
     *     total_projects: int,
     *     active_projects: int,
     *     completed_projects: int,
     *     on_hold_projects: int,
     *     overdue_projects: int,
     *     by_status: list<array{status: string, count: int}>,
     *     total_budget: float,
     *     total_tasks: int,
     *     open_tasks: int,
     *     completed_tasks: int,
     *     overdue_tasks: int,
     *     tasks_by_status: list<array{status: string, count: int}>,
     *     task_completion_rate: float,
     *     total_milestones: int,
     *     upcoming_milestones: int,
     *     overdue_milestones: int
     * }
     */
    public function summary(int $companyId): array
    {
        $today = now()->startOfDay();
        $todayDate = $today->toDateString();

        $projects = Project::where('company_id', $companyId);
        $tasks = ProjectTask::where('company_id', $companyId);
        $milestones = ProjectMilestone::where('company_id', $companyId);

        $byStatus = array_fill_keys(ProjectStatus::values(), 0);

        foreach ((clone $projects)->selectRaw('status, count(*) as aggregate')->groupBy('status')->get() as $row) {
            $status = $row->getAttribute('status');
            $status = $status instanceof ProjectStatus ? $status->value : (string) $status;

            if (array_key_exists($status, $byStatus)) {
                $byStatus[$status] = (int) $row->getAttribute('aggregate');
            }
        }

        $tasksByStatus = array_fill_keys(ProjectTask::STATUSES, 0);

        foreach ((clone $tasks)->selectRaw('status, count(*) as aggregate')->groupBy('status')->get() as $row) {
            $status = (string) $row->getAttribute('status');

            if (array_key_exists($status, $tasksByStatus)) {
                $tasksByStatus[$status] = (int) $row->getAttribute('aggregate');
            }
        }

        $totalTasks = array_sum($tasksByStatus);
        $completedTasks = $tasksByStatus[ProjectTask::STATUS_DONE];

        return [
            'total_projects' => (clone $projects)->count(),
            'active_projects' => (clone $projects)->where('status', ProjectStatus::Active->value)->count(),
            'completed_projects' => (clone $projects)->where('status', ProjectStatus::Completed->value)->count(),
            'on_hold_projects' => (clone $projects)->where('status', ProjectStatus::OnHold->value)->count(),
            'overdue_projects' => (clone $projects)
                ->whereDate('end_date', '<', $todayDate)
                ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
                ->count(),
            'by_status' => $this->zeroFilled($byStatus),
            'total_budget' => round((float) (clone $projects)->sum('budget'), 2),
            'total_tasks' => $totalTasks,
            'open_tasks' => (clone $tasks)->whereIn('status', ProjectTask::OPEN_STATUSES)->count(),
            'completed_tasks' => $completedTasks,
            'overdue_tasks' => (clone $tasks)
                ->whereIn('status', ProjectTask::OPEN_STATUSES)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $todayDate)
                ->count(),
            'tasks_by_status' => $this->zeroFilled($tasksByStatus),
            'task_completion_rate' => $totalTasks === 0
                ? 0.0
                : round($completedTasks * 100 / $totalTasks, 2),
            'total_milestones' => (clone $milestones)->count(),
            'upcoming_milestones' => (clone $milestones)
                ->where('status', ProjectMilestone::STATUS_PENDING)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', $todayDate)
                ->count(),
            'overdue_milestones' => (clone $milestones)
                ->where('status', ProjectMilestone::STATUS_PENDING)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $todayDate)
                ->count(),
        ];
    }

    /**
     * Preserve the declaration order of the zero-filled keys when rendering a
     * status breakdown.
     *
     * @param  array<string, int>  $counts
     * @return list<array{status: string, count: int}>
     */
    private function zeroFilled(array $counts): array
    {
        $result = [];

        foreach ($counts as $status => $count) {
            $result[] = ['status' => $status, 'count' => $count];
        }

        return $result;
    }
}
