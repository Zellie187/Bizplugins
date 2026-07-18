<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Companies\Entities;

use BizHub\Companies\Entities\Director;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DirectorTest extends TestCase
{
    public function test_requires_id_or_passport_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Director(Uuid::generate(), 'Jane', 'Doe', null, null, new DateTimeImmutable());
    }

    public function test_full_name(): void
    {
        $director = new Director(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, new DateTimeImmutable());

        $this->assertSame('Jane Doe', $director->getFullName());
    }

    public function test_resign_deactivates_director(): void
    {
        $appointment = new DateTimeImmutable('2020-01-01');
        $director = new Director(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, $appointment);

        $director->resign(new DateTimeImmutable('2026-01-01'));

        $this->assertFalse($director->isActive());
        $this->assertNotNull($director->getResignationDate());
    }

    public function test_resign_before_appointment_rejected(): void
    {
        $appointment = new DateTimeImmutable('2026-01-01');
        $director = new Director(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, $appointment);

        $this->expectException(InvalidArgumentException::class);

        $director->resign(new DateTimeImmutable('2020-01-01'));
    }

    public function test_reactivate(): void
    {
        $director = new Director(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, new DateTimeImmutable('2020-01-01'));
        $director->resign(new DateTimeImmutable('2026-01-01'));
        $director->reactivate();

        $this->assertTrue($director->isActive());
        $this->assertNull($director->getResignationDate());
    }
}
