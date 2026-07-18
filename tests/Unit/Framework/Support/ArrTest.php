<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Framework\Support;

use BizHub\Framework\Support\Arr;
use PHPUnit\Framework\TestCase;

final class ArrTest extends TestCase
{
    public function test_get_supports_dot_notation(): void
    {
        $this->assertSame(1, Arr::get(['a' => ['b' => 1]], 'a.b'));
    }

    public function test_get_returns_default_when_missing(): void
    {
        $this->assertSame('fallback', Arr::get(['a' => []], 'a.b', 'fallback'));
    }

    public function test_has_detects_nested_keys(): void
    {
        $this->assertTrue(Arr::has(['a' => ['b' => null]], 'a.b'));
        $this->assertFalse(Arr::has(['a' => []], 'a.b'));
    }

    public function test_set_writes_nested_keys(): void
    {
        $result = Arr::set([], 'a.b.c', 'value');

        $this->assertSame(['a' => ['b' => ['c' => 'value']]], $result);
    }

    public function test_only_and_except(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->assertSame(['a' => 1, 'b' => 2], Arr::only($array, ['a', 'b']));
        $this->assertSame(['c' => 3], Arr::except($array, ['a', 'b']));
    }

    public function test_pluck(): void
    {
        $rows = [['name' => 'a'], ['name' => 'b']];

        $this->assertSame(['a', 'b'], Arr::pluck($rows, 'name'));
    }

    public function test_wrap(): void
    {
        $this->assertSame([1, 2], Arr::wrap([1, 2]));
        $this->assertSame(['x'], Arr::wrap('x'));
        $this->assertSame([], Arr::wrap(null));
    }
}
