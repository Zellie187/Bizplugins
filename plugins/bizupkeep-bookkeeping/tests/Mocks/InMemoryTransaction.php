<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Mocks;

use BizHub\Framework\Database\Contracts\TransactionInterface;

/**
 * In-memory TransactionInterface implementation for tests.
 *
 * Real transaction semantics aren't needed against InMemoryDatabase
 * (there is no real rollback to test at that layer - InMemoryDatabase
 * has no concept of an uncommitted write), but JournalRepository's
 * constructor requires a TransactionInterface, and its
 * insertEntryWithLines() logic (call the callback, let exceptions
 * propagate) is exactly what real callers depend on - so this fake
 * still exercises that control flow faithfully.
 */
final class InMemoryTransaction implements TransactionInterface
{
    private bool $active = false;

    public function transactional(callable $callback): mixed
    {
        return $callback();
    }

    public function begin(): void
    {
        $this->active = true;
    }

    public function commit(): void
    {
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->active = false;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
