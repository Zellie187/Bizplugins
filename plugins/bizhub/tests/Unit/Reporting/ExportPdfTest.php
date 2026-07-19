<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Reporting;

use BizHub\Reporting\ExportPdf;
use PHPUnit\Framework\TestCase;

final class ExportPdfTest extends TestCase
{
    public function test_to_html_includes_title_and_rows(): void
    {
        $html = (new ExportPdf())->toHtml('Company Report', [
            ['name' => 'Acme', 'status' => 'active'],
        ]);

        $this->assertStringContainsString('<title>Company Report</title>', $html);
        $this->assertStringContainsString('Acme', $html);
        $this->assertStringContainsString('active', $html);
    }
}
