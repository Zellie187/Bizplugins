<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Reporting;

use BizHub\Reporting\ExportCsv;
use PHPUnit\Framework\TestCase;

final class ExportCsvTest extends TestCase
{
    public function test_to_string_produces_valid_csv(): void
    {
        $csv = new ExportCsv();

        $result = $csv->toString([
            ['name' => 'Acme', 'status' => 'active'],
            ['name' => 'Beta', 'status' => 'pending'],
        ]);

        $this->assertStringContainsString("name,status", $result);
        $this->assertStringContainsString("Acme,active", $result);
        $this->assertStringContainsString("Beta,pending", $result);
    }

    public function test_empty_rows_produce_empty_string(): void
    {
        $this->assertSame('', (new ExportCsv())->toString([]));
    }
}
