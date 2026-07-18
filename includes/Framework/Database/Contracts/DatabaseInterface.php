<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Contracts;

/**
 * Defines the primary database abstraction.
 *
 * All repositories must depend on this interface rather than
 * directly interacting with the WordPress database layer.
 *
 * @package BizHub\Framework\Database\Contracts
 */
interface DatabaseInterface
{
    /**
     * Find a single record.
     *
     * @param string               $table
     * @param array<string,mixed>  $criteria
     *
     * @return array<string,mixed>|null
     */
    public function findOne(
        string $table,
        array $criteria
    ): ?array;

    /**
     * Find multiple records.
     *
     * @param string               $table
     * @param array<string,mixed>  $criteria
     * @param array<string,string> $orderBy
     * @param int|null             $limit
     * @param int                  $offset
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAll(
        string $table,
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): array;

    /**
     * Insert a record.
     *
     * @param string              $table
     * @param array<string,mixed> $data
     *
     * @return int
     */
    public function insert(
        string $table,
        array $data
    ): int;

    /**
     * Update records.
     *
     * @param string              $table
     * @param array<string,mixed> $data
     * @param array<string,mixed> $criteria
     *
     * @return int
     */
    public function update(
        string $table,
        array $data,
        array $criteria
    ): int;

    /**
     * Delete records.
     *
     * @param string              $table
     * @param array<string,mixed> $criteria
     *
     * @return int
     */
    public function delete(
        string $table,
        array $criteria
    ): int;

    /**
     * Determine if a record exists.
     *
     * @param string              $table
     * @param array<string,mixed> $criteria
     */
    public function exists(
        string $table,
        array $criteria
    ): bool;

    /**
     * Execute a raw SQL query.
     *
     * @param string              $sql
     * @param array<int,mixed>    $parameters
     *
     * @return array<int,array<string,mixed>>
     */
    public function query(
        string $sql,
        array $parameters = []
    ): array;

    /**
     * Execute a non-select statement.
     *
     * @param string           $sql
     * @param array<int,mixed> $parameters
     *
     * @return int
     */
    public function execute(
        string $sql,
        array $parameters = []
    ): int;

    /**
     * Start a transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit the transaction.
     */
    public function commit(): void;

    /**
     * Roll back the transaction.
     */
    public function rollBack(): void;

    /**
     * Create a query builder.
     */
    public function createQueryBuilder(): QueryBuilderInterface;
}
