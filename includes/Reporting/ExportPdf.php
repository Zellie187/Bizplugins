<?php

declare(strict_types=1);

namespace BizHub\Reporting;

/**
 * Exports tabular report data as print-ready HTML.
 *
 * No PDF rendering library (e.g. Dompdf, mPDF) is currently a project
 * dependency, so this produces a self-contained, print-styled HTML
 * document rather than a true binary PDF. Browsers can save this as a
 * PDF via their native "Print to PDF" option. Swap this implementation
 * for a real PDF library if server-side PDF generation becomes a
 * requirement.
 *
 * @package BizHub\Reporting
 */
final class ExportPdf
{
    /**
     * Render tabular data as a print-ready HTML document.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string>|null         $headers
     */
    public function toHtml(string $title, array $rows, ?array $headers = null): string
    {
        $headers ??= $rows === [] ? [] : array_keys($rows[0]);

        $html = '<!doctype html><html><head><meta charset="utf-8">';
        $html .= '<title>' . htmlspecialchars($title) . '</title>';
        $html .= '<style>body{font-family:sans-serif;margin:2em;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;}@media print{body{margin:0;}}</style>';
        $html .= '</head><body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<table><thead><tr>';

        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars((string) $header) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $html .= '<td>' . htmlspecialchars(is_scalar($value) ? (string) $value : json_encode($value)) . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        return $html;
    }
}
