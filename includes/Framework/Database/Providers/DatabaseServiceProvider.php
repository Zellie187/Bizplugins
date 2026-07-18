<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Providers;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Database\Contracts\TransactionInterface;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * Database Service Provider.
 *
 * Exposes the database connection and transaction manager to the
 * rest of the application. All bindings are declared in
 * Database/definitions.php; this provider only performs boot-time
 * wiring.
 *
 * @package BizHub\Framework\Database\Providers
 */
final class DatabaseServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly TransactionInterface $transaction
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
        // Bindings are declared in Database/definitions.php.
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
    }

    /**
     * Return the database connection.
     */
    public function database(): DatabaseInterface
    {
        return $this->database;
    }

    /**
     * Return the transaction manager.
     */
    public function transaction(): TransactionInterface
    {
        return $this->transaction;
    }
}
