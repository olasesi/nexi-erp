<?php

namespace App\Services;

use App\Services\Import\RowImporter;
use App\Support\Csv;
use Illuminate\Support\Facades\DB;

class BulkImportService
{
    /**
     * Run a CSV payload through an importer, reporting per-row outcomes.
     * A dry run performs the exact same work inside a transaction that is
     * rolled back, so the summary stays accurate without persisting rows.
     *
     * @return array{created: int, updated: int, failed: int, errors: array<int, array{line: int, message: string}>}
     */
    public function run(RowImporter $importer, string $csv, int $companyId, bool $dryRun = false): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $parsed = Csv::parse($csv);

        if ($parsed['headers'] === []) {
            $result['failed'] = 1;
            $result['errors'][] = ['line' => 1, 'message' => 'The CSV payload is empty.'];

            return $result;
        }

        $missing = array_diff($importer->columns(), $parsed['headers']);

        if ($missing !== []) {
            $result['failed'] = 1;
            $result['errors'][] = [
                'line' => 1,
                'message' => 'Missing required columns: '.implode(', ', $missing).'.',
            ];

            return $result;
        }

        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($parsed['rows'] as $row) {
                $values = [];

                foreach ($parsed['headers'] as $index => $header) {
                    $values[$header] = trim((string) ($row['values'][$index] ?? ''));
                }

                if ($error = $importer->error($values)) {
                    $result['failed']++;
                    $result['errors'][] = ['line' => $row['line'], 'message' => $error];

                    continue;
                }

                $outcome = $importer->import($values, $companyId);

                if ($outcome === RowImporter::UPDATED) {
                    $result['updated']++;
                } else {
                    $result['created']++;
                }
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        return $result;
    }
}
