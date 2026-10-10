<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Project;
use App\Models\SmartReport;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Executes a saved {@see SmartReport} against its live data source.
 *
 * Every identifier is whitelisted against the real table columns before it
 * reaches the query builder, so a report definition can never inject arbitrary
 * SQL. Filters, grouping and aggregation are the only operations offered.
 */
class SmartReportRunner
{
    /** Hard cap on the number of rows a single run may return. */
    public const MAX_ROWS = 500;

    /**
     * Supported sources mapped onto their Eloquent models.
     *
     * @var array<string, class-string<Model>>
     */
    public const SOURCE_MODELS = [
        'leads' => Lead::class,
        'deals' => Deal::class,
        'invoices' => Invoice::class,
        'projects' => Project::class,
        'tickets' => Ticket::class,
        'expenses' => Expense::class,
        'contacts' => Contact::class,
        'products' => Product::class,
    ];

    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'like', 'not like'];

    /**
     * Run a report and return its tabular result.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, total: int}
     */
    public function run(SmartReport $report, int $limit = self::MAX_ROWS): array
    {
        $limit = max(1, min($limit, self::MAX_ROWS));

        $modelClass = $this->resolveModelClass($report->source);

        if ($modelClass === null) {
            return ['columns' => [], 'rows' => [], 'total' => 0];
        }

        $model = new $modelClass;
        $table = $model->getTable();
        $available = array_values(Schema::getColumnListing($table));

        $query = $model->newQuery();
        $this->applyFilters($query, (array) ($report->filters ?? []), $available);

        if ($report->group_by !== null && $report->group_by !== '') {
            return $this->runGrouped($query, $report, $available, $limit);
        }

        return $this->runRaw($query, $report, $available, $limit);
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $source): ?string
    {
        return self::SOURCE_MODELS[$source] ?? null;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $available
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, total: int}
     */
    private function runGrouped(Builder $query, SmartReport $report, array $available, int $limit): array
    {
        $groupBy = $report->group_by;

        if (! in_array($groupBy, $available, true)) {
            return ['columns' => [], 'rows' => [], 'total' => 0];
        }

        $aggregate = in_array($report->aggregate, SmartReport::AGGREGATES, true)
            ? $report->aggregate
            : 'count';

        $field = $report->aggregate_field;

        if ($field !== null && ! in_array($field, $available, true)) {
            $field = null;
        }

        if ($aggregate !== 'count' && $field === null) {
            $aggregate = 'count';
        }

        $grammar = $query->getQuery()->getGrammar();
        $target = $field === null ? '*' : $grammar->wrap($field);
        $alias = $grammar->wrap($aggregate);

        $rows = $query
            ->select($groupBy)
            ->selectRaw("{$aggregate}({$target}) as {$alias}")
            ->groupBy($groupBy)
            ->orderBy($groupBy)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'columns' => [$groupBy, $aggregate],
            'rows' => array_values($rows),
            'total' => count($rows),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $available
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, total: int}
     */
    private function runRaw(Builder $query, SmartReport $report, array $available, int $limit): array
    {
        $requested = is_array($report->columns) && $report->columns !== []
            ? $report->columns
            : $available;

        $columns = array_values(array_intersect($requested, $available));

        if ($columns === []) {
            $columns = $available;
        }

        $total = (clone $query)->count();

        $rows = $query
            ->select($columns)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'columns' => $columns,
            'rows' => array_values($rows),
            'total' => $total,
        ];
    }

    /**
     * Apply each report filter, skipping any column the table does not own.
     *
     * Accepts either `[['column' => 'status', 'operator' => '=', 'value' => 'new']]`
     * or the associative shorthand `['status' => 'new']`.
     *
     * @param  Builder<Model>  $query
     * @param  array<mixed>  $filters
     * @param  list<string>  $available
     */
    private function applyFilters(Builder $query, array $filters, array $available): void
    {
        foreach ($filters as $key => $filter) {
            if (is_array($filter) && array_key_exists('column', $filter)) {
                $column = $filter['column'];
                $operator = $filter['operator'] ?? '=';
                $value = $filter['value'] ?? null;
            } elseif (is_string($key)) {
                $column = $key;
                $operator = '=';
                $value = $filter;
            } else {
                continue;
            }

            if (! is_string($column) || ! in_array($column, $available, true)) {
                continue;
            }

            $operator = strtolower(trim((string) $operator));

            if (in_array($operator, ['in', 'not in', 'not_in'], true)) {
                if (! is_array($value)) {
                    continue;
                }

                $query->whereIn($column, $value, 'and', $operator !== 'in');

                continue;
            }

            if (! in_array($operator, self::OPERATORS, true)) {
                $operator = '=';
            }

            $query->where($column, $operator, $value);
        }
    }
}
