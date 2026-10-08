<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AddonService;
use App\Services\BusinessHealthService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Nightly scoring pass for the AI Business Advisor. Each company that has the
 * add-on switched on gets one snapshot per day (upserted, so a re-run corrects
 * today rather than adding a second row) and critical alerts become in-app
 * notifications.
 */
class SnapshotBusinessHealth extends Command
{
    private const ADDON = 'business-advisor';

    protected $signature = 'advisor:snapshot
                            {--company= : Score a single company instead of all of them}';

    protected $description = 'Score every company for the AI Business Advisor and store today’s snapshot';

    public function handle(BusinessHealthService $advisor, AddonService $addons): int
    {
        $only = $this->option('company');

        if ($only !== null) {
            $companies = [(int) $only];

            if (! Company::query()->whereKey($companies[0])->exists()) {
                $this->error(sprintf('Company %d does not exist.', $companies[0]));

                return self::FAILURE;
            }
        } else {
            $companies = Company::query()
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        $scored = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($companies as $companyId) {
            if (! $addons->enabled(self::ADDON, $companyId)) {
                $skipped++;

                continue;
            }

            try {
                $advisor->record($companyId);
                $scored++;
            } catch (Throwable $exception) {
                $failed++;
                $this->warn(sprintf('Company %d could not be scored: %s', $companyId, $exception->getMessage()));
            }
        }

        $this->info(sprintf('Scored %d company/companies, %d skipped, %d failed.', $scored, $skipped, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
