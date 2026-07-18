<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Framework\Support;

use BizHub\Framework\Support\Str;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    public function test_contains_starts_with_ends_with(): void
    {
        $this->assertTrue(Str::contains('hello world', 'wor'));
        $this->assertTrue(Str::startsWith('hello world', 'hello'));
        $this->assertTrue(Str::endsWith('hello world', 'world'));
        $this->assertFalse(Str::contains('hello', 'xyz'));
    }

    public function test_snake_camel_studly(): void
    {
        $this->assertSame('hello_world', Str::snake('HelloWorld'));
        $this->assertSame('helloWorld', Str::camel('hello world'));
        $this->assertSame('HelloWorld', Str::studly('hello_world'));
    }

    public function test_slug(): void
    {
        $this->assertSame('hello-world', Str::slug('Hello World!'));
    }

    public function test_limit(): void
    {
        $this->assertSame('hello...', Str::limit('hello world', 5));
        $this->assertSame('hi', Str::limit('hi', 5));
    }

    public function test_random_generates_requested_length(): void
    {
        $this->assertSame(16, strlen(Str::random(16)));
        $this->assertNotSame(Str::random(16), Str::random(16));
    }
}
