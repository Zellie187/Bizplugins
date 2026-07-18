<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Contracts;

/**
 * Defines a database transaction.
 *
 * Implementations provide transactional support independent of the
 * underlying database driver.
 *
 * Transactions should be obtained from the DatabaseInterface and
 * disposed of after commit or rollback.
 *
 * @package BizHub\Framework\Database\Contracts
 */
interface TransactionInterface
{
    /**
     * Begin the transaction.
     *
     * Calling begin() on an already active transaction should throw
     * an exception.
     */
    public function begin(): void;

    /**
     * Commit the transaction.
     *
     * @throws \RuntimeException
     */
    public function commit(): void;

    /**
     * Roll back the transaction.
     *
     * @throws \RuntimeException
     */
    public function rollBack(): void;

    /**
     * Determine whether the transaction is active.
     */
    public function isActive(): bool;

    /**
     * Execute a callback inside a transaction.
     *
     * If the callback throws an exception the transaction MUST be
     * rolled back before rethrowing the exception.
     *
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function transactional(
        callable $callback
    ): mixed;
}
