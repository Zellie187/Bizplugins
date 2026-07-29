<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Export;

/**
 * Read-side counterpart to CsvWriter, for bank statement import.
 * Every str_getcsv() call passes delimiter/enclosure/escape explicitly
 * for the same reason CsvWriter's fputcsv() calls do - omitting them
 * triggers a PHP 8.4+ deprecation notice, since the default is
 * documented to change in a future PHP version.
 *
 * @package BizHub\Bookkeeping\Export
 */
final class CsvReader
{
    /**
     * @return array<int,string>
     */
    public function readHeaders(string $csvContent): array
    {
        $lines = $this->splitLines($csvContent);

        return $lines === [] ? [] : str_getcsv($lines[0], ',', '"', '\\');
    }

    /**
     * @return array<int,array<string,string>>
     */
    public function readRows(string $csvContent): array
    {
        $lines = $this->splitLines($csvContent);

        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv($lines[0], ',', '"', '\\');
        $rows = [];

        foreach (array_slice($lines, 1) as $line) {
            $values = str_getcsv($line, ',', '"', '\\');
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = $values[$index] ?? '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int,string>
     */
    private function splitLines(string $csvContent): array
    {
        // Strip a UTF-8 BOM - common in bank exports that have been
        // opened and re-saved in Excel, which would otherwise corrupt
        // the first header's name.
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent) ?? $csvContent;

        $normalized = str_replace("\r\n", "\n", $csvContent);
        $lines = explode("\n", trim($normalized));

        return array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
    }
}
