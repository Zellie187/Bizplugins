<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Companies\Entities;

use BizHub\Companies\Entities\Shareholder;
use BizHub\Framework\Support\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ShareholderTest extends TestCase
{
    public function test_requires_id_or_passport_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Shareholder(Uuid::generate(), Uuid::generate(), 'Jane Doe', null, null, 50.0);
    }

    public function test_requires_company_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Shareholder(Uuid::generate(), '', 'Jane Doe', '8001015800086', null, 50.0);
    }

    public function test_rejects_shares_percentage_above_100(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Shareholder(Uuid::generate(), Uuid::generate(), 'Jane Doe', '8001015800086', null, 100.01);
    }

    public function test_rejects_negative_shares_percentage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Shareholder(Uuid::generate(), Uuid::generate(), 'Jane Doe', '8001015800086', null, -1);
    }

    public function test_getters(): void
    {
        $companyUuid = Uuid::generate();
        $shareholder = new Shareholder(Uuid::generate(), $companyUuid, 'Jane Doe', '8001015800086', null, 60.0);

        $this->assertSame($companyUuid, $shareholder->getCompanyUuid());
        $this->assertSame('Jane Doe', $shareholder->getFullName());
        $this->assertSame('8001015800086', $shareholder->getIdNumber());
        $this->assertNull($shareholder->getPassportNumber());
        $this->assertSame(60.0, $shareholder->getSharesPercentage());
    }

    public function test_set_shares_percentage(): void
    {
        $shareholder = new Shareholder(Uuid::generate(), Uuid::generate(), 'Jane Doe', '8001015800086', null, 60.0);

        $shareholder->setSharesPercentage(40.0);

        $this->assertSame(40.0, $shareholder->getSharesPercentage());
    }

    public function test_set_shares_percentage_rejects_out_of_range(): void
    {
        $shareholder = new Shareholder(Uuid::generate(), Uuid::generate(), 'Jane Doe', '8001015800086', null, 60.0);

        $this->expectException(InvalidArgumentException::class);

        $shareholder->setSharesPercentage(101);
    }

    public function test_set_full_name(): void
    {
        $shareholder = new Shareholder(Uuid::generate(), Uuid::generate(), 'Jane Doe', '8001015800086', null, 60.0);

        $shareholder->setFullName('Jane Smith');

        $this->assertSame('Jane Smith', $shareholder->getFullName());
    }

    public function test_set_full_name_rejects_empty(): void
    {
        $shareholder = new Shareholder(Uuid::generate(), Uuid::generate(), 'Jane Doe', '8001015800086', null, 60.0);

        $this->expectException(InvalidArgumentException::class);

        $shareholder->setFullName('   ');
    }
}
