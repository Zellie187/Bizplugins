<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Export;

use RuntimeException;

/**
 * Local port of BizHub core's Reporting\ExportCsv pattern
 * (fputcsv()/php://temp) - not depended on cross-plugin since that
 * class isn't exposed via an interface from bizhub core.
 *
 * @package BizHub\Bookkeeping\Export
 */
final class CsvWriter
{
    /**
     * @param array<int,array<string,scalar|null>> $rows
     * @param array<int,string>|null                $headers Derived from the first row's keys if omitted.
     */
    public function toString(array $rows, ?array $headers = null): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a temporary stream for CSV writing.');
        }

        $headers ??= $rows === [] ? [] : array_keys($rows[0]);

        // Every argument is passed explicitly (not just delimiter) so
        // output is stable across PHP versions - omitting $escape
        // triggers a deprecation notice as of PHP 8.4+ and its default
        // is documented to change in a future PHP version.
        if ($headers !== []) {
            fputcsv($handle, $headers, ',', '"', '\\');
        }

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $row
            ), ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
