<?php

declare(strict_types=1);

namespace BizHub\Reporting;

/**
 * Exports tabular report data as CSV.
 *
 * @package BizHub\Reporting
 */
final class ExportCsv
{
    /**
     * Convert an array of rows into a CSV string.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string>|null         $headers Column headers; derived from the first row if omitted.
     */
    public function toString(array $rows, ?array $headers = null): string
    {
        if ($rows === []) {
            return '';
        }

        $headers ??= array_keys($rows[0]);

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $headers, escape: '\\');

        foreach ($rows as $row) {
            $line = [];

            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $line[] = is_scalar($value) ? (string) $value : json_encode($value);
            }

            fputcsv($handle, $line, escape: '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Stream a CSV file directly to the browser as a download.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string>|null         $headers
     */
    public function download(string $filename, array $rows, ?array $headers = null): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo $this->toString($rows, $headers);
    }
}
