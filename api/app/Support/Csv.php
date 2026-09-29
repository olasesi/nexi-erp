<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Parse a CSV document into normalized headers plus one entry per data
     * row, keeping the source line number so errors can point at the file.
     *
     * @return array{headers: array<int, string>, rows: array<int, array{line: int, values: array<int, string>}>}
     */
    public static function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line) => trim($line) !== ''));

        $headerLine = array_shift($lines);

        if ($headerLine === null) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(
            fn (?string $header) => str_replace(' ', '_', strtolower(trim((string) $header))),
            str_getcsv($headerLine)
        );

        $rows = [];

        foreach ($lines as $index => $line) {
            $rows[] = [
                'line' => $index + 2,
                'values' => array_map(fn (?string $value) => (string) $value, str_getcsv($line)),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Stream a CSV download for the given columns, writing one line per row.
     *
     * @param  array<int, string>  $columns
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
