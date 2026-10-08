<?php

namespace App\Services;

use App\Models\CrmStage;
use App\Models\Deal;
use App\Models\Lead;

/**
 * Shared CRM aggregates behind GET /dashboard/crm-summary and the Smart
 * Dashboard's sales tab. Every method runs a fixed number of grouped
 * statements, so the cost does not grow with the number of leads or deals.
 */
class CrmAnalytics
{
    /**
     * Lead funnel, zero-filled across every status so the chart keeps its
     * category order even for statuses with no rows.
     *
     * @return list<array{status: string, count: int}>
     */
    public function statusCounts(int $companyId): array
    {
        $counts = array_fill_keys(Lead::STATUSES, 0);

        $rows = Lead::where('company_id', $companyId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $status = (string) $row->getAttribute('status');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row->getAttribute('total');
            }
        }

        return array_map(
            fn (string $status): array => ['status' => $status, 'count' => $counts[$status]],
            array_keys($counts),
        );
    }

    /**
     * Leads per acquisition source, zero-filled in the documented order.
     *
     * @return list<array{source: string, count: int}>
     */
    public function sourceCounts(int $companyId): array
    {
        $counts = array_fill_keys(Lead::SOURCES, 0);

        $rows = Lead::where('company_id', $companyId)
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->get();

        foreach ($rows as $row) {
            $source = (string) $row->getAttribute('source');

            if (array_key_exists($source, $counts)) {
                $counts[$source] = (int) $row->getAttribute('total');
            }
        }

        return array_map(
            fn (string $source): array => ['source' => $source, 'count' => $counts[$source]],
            array_keys($counts),
        );
    }

    /**
     * The same source breakdown plus how many of each source's leads
     * converted, which is what the Smart Dashboard's source table shows.
     *
     * @return list<array{source: string, count: int, converted: int, conversion_rate: float}>
     */
    public function sourceDetail(int $companyId): array
    {
        $totals = array_fill_keys(Lead::SOURCES, ['count' => 0, 'converted' => 0]);

        $rows = Lead::where('company_id', $companyId)
            ->selectRaw('source, count(*) as total, sum(case when converted_at is not null then 1 else 0 end) as converted')
            ->groupBy('source')
            ->get();

        foreach ($rows as $row) {
            $totals[(string) $row->getAttribute('source')] = [
                'count' => (int) $row->getAttribute('total'),
                'converted' => (int) ($row->getAttribute('converted') ?? 0),
            ];
        }

        return array_map(fn (string $source, array $row): array => [
            'source' => $source,
            'count' => $row['count'],
            'converted' => $row['converted'],
            'conversion_rate' => $row['count'] === 0
                ? 0.0
                : round($row['converted'] * 100 / $row['count'], 2),
        ], array_keys($totals), $totals);
    }

    /**
     * Open deals spread across the ordered pipeline. One grouped query plus
     * one ordered stage read keeps this flat no matter how many deals exist.
     *
     * @return list<array{stage_id: int, stage: string, open_deals: int, pipeline_value: float}>
     */
    public function pipelineByStage(int $companyId): array
    {
        $aggregates = Deal::where('company_id', $companyId)
            ->where('status', Deal::STATUS_OPEN)
            ->groupBy('crm_stage_id')
            ->selectRaw('crm_stage_id, count(*) as open_deals, coalesce(sum(value), 0) as pipeline_value')
            ->get()
            ->keyBy('crm_stage_id');

        $stages = CrmStage::where('company_id', $companyId)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'name']);

        return array_values($stages->map(function (CrmStage $stage) use ($aggregates) {
            $row = $aggregates->get($stage->getKey());

            return [
                'stage_id' => (int) $stage->getKey(),
                'stage' => (string) $stage->name,
                'open_deals' => $row === null ? 0 : (int) $row->getAttribute('open_deals'),
                'pipeline_value' => round((float) ($row?->getAttribute('pipeline_value') ?? 0), 2),
            ];
        })->all());
    }

    /**
     * Leads created per month over the last $months calendar months, with
     * how many of them converted. Zero-filled so a quiet month still gets a
     * column.
     *
     * @return list<array{month: string, total: int, converted: int}>
     */
    public function monthlyVolume(int $companyId, int $months): array
    {
        $from = now()->startOfDay()->subMonths($months - 1)->startOfMonth();
        $labels = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $labels[] = $from->copy()->addMonthsNoOverflow($offset)->format('Y-m');
        }

        $rows = Lead::where('company_id', $companyId)
            ->whereDate('created_at', '>=', $from->toDateString())
            ->selectRaw('substr(created_at, 1, 7) as month, count(*) as total, sum(case when converted_at is not null then 1 else 0 end) as converted')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        return array_map(fn (string $label): array => [
            'month' => $label,
            'total' => (int) ($rows->get($label)?->getAttribute('total') ?? 0),
            'converted' => (int) ($rows->get($label)?->getAttribute('converted') ?? 0),
        ], $labels);
    }

    /**
     * The handful of numbers both dashboards headline with.
     *
     * @return array{active_leads: int, open_deals: int, pipeline_value: float, conversion_rate: float}
     */
    public function kpis(int $companyId): array
    {
        $summary = $this->summary($companyId);

        return [
            'active_leads' => $summary['active_leads'],
            'open_deals' => $summary['open_deals'],
            'pipeline_value' => $summary['pipeline_value'],
            'conversion_rate' => $summary['conversion_rate'],
        ];
    }

    /**
     * Scalar CRM aggregates for one company. Conversion is the won share of
     * decided deals: open work never dilutes it.
     *
     * @return array{total_leads: int, active_leads: int, converted_leads: int, lost_leads: int, overdue_follow_ups: int, open_deals: int, pipeline_value: float, won_deals: int, won_value: float, lost_deals: int, conversion_rate: float}
     */
    public function summary(int $companyId): array
    {
        $leads = Lead::where('company_id', $companyId);
        $deals = Deal::where('company_id', $companyId);

        $openDeals = (clone $deals)->where('status', Deal::STATUS_OPEN);
        $openDealCount = $openDeals->count();

        $wonDeals = (clone $deals)->where('status', Deal::STATUS_WON);
        $wonDealCount = $wonDeals->count();
        $lostDealCount = (clone $deals)->where('status', Deal::STATUS_LOST)->count();

        $decided = $wonDealCount + $lostDealCount;

        return [
            'total_leads' => (clone $leads)->count(),
            'active_leads' => (clone $leads)->active()->count(),
            'converted_leads' => (clone $leads)->where('status', Lead::STATUS_CONVERTED)->count(),
            'lost_leads' => (clone $leads)->where('status', Lead::STATUS_LOST)->count(),
            'overdue_follow_ups' => (clone $leads)
                ->active()
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<', now())
                ->count(),
            'open_deals' => $openDealCount,
            'pipeline_value' => round((float) (clone $openDeals)->sum('value'), 2),
            'won_deals' => $wonDealCount,
            'won_value' => round((float) $wonDeals->sum('value'), 2),
            'lost_deals' => $lostDealCount,
            'conversion_rate' => $decided === 0 ? 0.0 : round($wonDealCount * 100 / $decided, 2),
        ];
    }
}
