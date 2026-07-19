<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Framework\Support;

use BizHub\Framework\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function test_generate_produces_valid_uuid(): void
    {
        $uuid = Uuid::generate();

        $this->assertTrue(Uuid::isValid($uuid));
    }

    public function test_generate_produces_unique_values(): void
    {
        $this->assertNotSame(Uuid::generate(), Uuid::generate());
    }

    public function test_is_valid_rejects_malformed_strings(): void
    {
        $this->assertFalse(Uuid::isValid('not-a-uuid'));
        $this->assertFalse(Uuid::isValid(''));
    }
}
