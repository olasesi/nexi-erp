<?php

namespace App\Services\Import;

interface RowImporter
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    /**
     * The CSV columns this importer understands.
     *
     * @return array<int, string>
     */
    public function columns(): array;

    /**
     * Validate a single row, returning null when it is acceptable.
     *
     * @param  array<string, string>  $row
     */
    public function error(array $row): ?string;

    /**
     * Persist a validated row, reporting whether it created or updated.
     *
     * @param  array<string, string>  $row
     */
    public function import(array $row, int $companyId): string;
}
