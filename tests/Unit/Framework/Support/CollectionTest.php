<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Framework\Support;

use BizHub\Framework\Support\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    public function test_map_filter_and_reduce(): void
    {
        $collection = Collection::make([1, 2, 3, 4]);

        $this->assertSame([2, 4, 6, 8], $collection->map(fn (int $n): int => $n * 2)->toArray());
        $this->assertSame([2, 4], array_values($collection->filter(fn (int $n): bool => $n % 2 === 0)->toArray()));
        $this->assertSame(10, $collection->reduce(fn (int $carry, int $n): int => $carry + $n, 0));
    }

    public function test_first_and_last(): void
    {
        $collection = Collection::make([1, 2, 3]);

        $this->assertSame(1, $collection->first());
        $this->assertSame(3, $collection->last());
        $this->assertSame(2, $collection->first(fn (int $n): bool => $n === 2));
    }

    public function test_empty_checks(): void
    {
        $this->assertTrue(Collection::make([])->isEmpty());
        $this->assertTrue(Collection::make([1])->isNotEmpty());
    }

    public function test_array_access(): void
    {
        $collection = Collection::make(['a' => 1]);
        $collection['b'] = 2;

        $this->assertSame(1, $collection['a']);
        $this->assertSame(2, $collection['b']);
        $this->assertTrue(isset($collection['a']));

        unset($collection['a']);
        $this->assertFalse(isset($collection['a']));
    }

    public function test_is_iterable(): void
    {
        $collection = Collection::make(['x', 'y']);
        $items = [];

        foreach ($collection as $item) {
            $items[] = $item;
        }

        $this->assertSame(['x', 'y'], $items);
        $this->assertCount(2, $collection);
    }
}
