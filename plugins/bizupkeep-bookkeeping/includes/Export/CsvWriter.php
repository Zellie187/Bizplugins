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
                static fn (mixed $value): string => self::escapeFormula(is_scalar($value) ? (string) $value : ''),
                $row
            ), ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Neutralize CSV formula injection (CWE-1236): a cell starting with
     * =, +, -, or @ is interpreted as a formula by Excel/LibreOffice/etc
     * when the exported file is opened, letting a client-supplied value
     * (e.g. a transaction description, or an imported bank statement's
     * own description column) execute arbitrary spreadsheet formulas on
     * whoever opens the export. Prefixing with a single quote forces
     * spreadsheet applications to treat the cell as plain text while
     * leaving the value itself unchanged for any non-spreadsheet
     * consumer of the CSV.
     *
     * A purely numeric value (e.g. "-800.00", every exporter's Amount
     * column) is exempted: it starts with "-" but is not a formula, and
     * quoting it would corrupt the exported figure for anyone importing
     * the CSV back into accounting software.
     */
    private static function escapeFormula(string $value): string
    {
        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'" . $value
            : $value;
    }
}
