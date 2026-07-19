<?php

declare(strict_types=1);

namespace BizHub\Framework\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Generic, array-backed collection.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ArrayAccess<TKey,TValue>
 * @implements IteratorAggregate<TKey,TValue>
 *
 * @package BizHub\Framework\Support
 */
final class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @param array<TKey,TValue> $items
     */
    public function __construct(
        private array $items = []
    ) {
    }

    /**
     * Create a collection from an array.
     *
     * @param array<TKey,TValue> $items
     *
     * @return self<TKey,TValue>
     */
    public static function make(array $items = []): self
    {
        return new self($items);
    }

    /**
     * Apply a callback to each item, returning a new collection.
     *
     * @param callable(TValue,TKey):mixed $callback
     *
     * @return self<int,mixed>
     */
    public function map(callable $callback): self
    {
        return new self(
            array_map($callback, $this->items, array_keys($this->items))
        );
    }

    /**
     * Filter the collection using a callback.
     *
     * Not annotated @return self<TKey,TValue>: TValue can't be marked
     * covariant (ArrayAccess::offsetSet() needs it in a contravariant
     * position), so PHPStan can't verify array_filter()'s result matches
     * that precisely - see the ignoreErrors entry in phpstan.neon.
     *
     * @param (callable(TValue,TKey):bool)|null $callback
     */
    public function filter(?callable $callback = null): self
    {
        if ($callback === null) {
            return new self(array_filter($this->items));
        }

        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Iterate over each item.
     *
     * @param callable(TValue,TKey):void $callback
     *
     * @return self<TKey,TValue>
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param callable(mixed,TValue):mixed $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Return the first item, optionally matching a callback.
     *
     * @param (callable(TValue,TKey):bool)|null $callback
     *
     * @return TValue|null
     */
    public function first(?callable $callback = null): mixed
    {
        foreach ($this->items as $key => $item) {
            if ($callback === null || $callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Return the last item in the collection.
     *
     * @return TValue|null
     */
    public function last(): mixed
    {
        if ($this->items === []) {
            return null;
        }

        return $this->items[array_key_last($this->items)];
    }

    /**
     * Determine whether the collection is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Determine whether the collection has at least one item.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Return the number of items in the collection.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Return the underlying items as an array.
     *
     * @return array<TKey,TValue>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Return the values of the collection, re-indexed.
     *
     * @return array<int,TValue>
     */
    public function values(): array
    {
        return array_values($this->items);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;

            return;
        }

        $this->items[$offset] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * {@inheritDoc}
     *
     * @return Traversable<TKey,TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
