<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Transactions;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Database\Contracts\TransactionInterface;
use BizHub\Framework\Database\Exceptions\TransactionException;
use Throwable;

/**
 * Database-driver-agnostic transaction wrapper.
 *
 * @package BizHub\Framework\Database\Transactions
 */
final class Transaction implements TransactionInterface
{
    private bool $active = false;

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function begin(): void
    {
        if ($this->active) {
            throw new TransactionException('Transaction is already active.');
        }

        $this->database->beginTransaction();

        $this->active = true;
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): void
    {
        if (! $this->active) {
            throw new TransactionException('No active transaction to commit.');
        }

        $this->database->commit();

        $this->active = false;
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack(): void
    {
        if (! $this->active) {
            throw new TransactionException('No active transaction to roll back.');
        }

        $this->database->rollBack();

        $this->active = false;
    }

    /**
     * {@inheritDoc}
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * {@inheritDoc}
     */
    public function transactional(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback();

            $this->commit();

            return $result;
        } catch (Throwable $throwable) {
            $this->rollBack();

            throw $throwable;
        }
    }
}
