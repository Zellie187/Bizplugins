<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Drivers;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Database\Contracts\QueryBuilderInterface;
use BizHub\Framework\Database\Exceptions\ConnectionException;
use BizHub\Framework\Database\Exceptions\QueryException;
use BizHub\Framework\Database\Exceptions\TransactionException;
use BizHub\Framework\Database\Query\DatabaseQueryBuilder;
use BizHub\Framework\Database\ValueObjects\QueryParameter;
use wpdb;

/**
 * WordPress ($wpdb) implementation of the database abstraction.
 *
 * This is the only class permitted to interact with $wpdb directly.
 * All other framework and module code must depend on DatabaseInterface.
 *
 * @package BizHub\Framework\Database\Drivers
 */
final class WordPressDatabase implements DatabaseInterface
{
    private bool $transactionActive = false;

    public function __construct(
        private readonly wpdb $wpdb
    ) {
        if (! $this->wpdb instanceof wpdb) {
            throw new ConnectionException('The WordPress database global ($wpdb) is not available.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findOne(string $table, array $criteria): ?array
    {
        $rows = $this->findAll($table, $criteria, [], 1);

        return $rows[0] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(
        string $table,
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): array {
        [$where, $bindings] = $this->buildWhere($criteria);

        $sql = sprintf('SELECT * FROM %s%s', $this->resolveTable($table), $where);

        if ($orderBy !== []) {
            $clauses = [];

            foreach ($orderBy as $column => $direction) {
                $sortDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $clauses[] = sprintf('%s %s', $this->quoteIdentifier($column), $sortDirection);
            }

            $sql .= ' ORDER BY ' . implode(', ', $clauses);
        }

        if ($limit !== null) {
            $sql .= sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        return $this->query($sql, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_map(
            fn (string $column): string => $this->quoteIdentifier($column),
            array_keys($data)
        );

        $placeholders = array_map(
            fn (mixed $value): string => (new QueryParameter($value))->placeholder(),
            array_values($data)
        );

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->resolveTable($table),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->execute($sql, array_values($data));

        return (int) $this->wpdb->insert_id;
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $table, array $data, array $criteria): int
    {
        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = sprintf('%s = %s', $this->quoteIdentifier($column), (new QueryParameter($value))->placeholder());
            $bindings[] = $value;
        }

        [$where, $whereBindings] = $this->buildWhere($criteria);

        $sql = sprintf('UPDATE %s SET %s%s', $this->resolveTable($table), implode(', ', $sets), $where);

        return $this->execute($sql, array_merge($bindings, $whereBindings));
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $table, array $criteria): int
    {
        [$where, $bindings] = $this->buildWhere($criteria);

        $sql = sprintf('DELETE FROM %s%s', $this->resolveTable($table), $where);

        return $this->execute($sql, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $table, array $criteria): bool
    {
        return $this->findOne($table, $criteria) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $parameters = []): array
    {
        $prepared = $this->prepare($sql, $parameters);

        $results = $this->wpdb->get_results($prepared, ARRAY_A);

        if ($this->wpdb->last_error !== '') {
            throw new QueryException($this->wpdb->last_error, $prepared);
        }

        return $results ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $parameters = []): int
    {
        $prepared = $this->prepare($sql, $parameters);

        $result = $this->wpdb->query($prepared);

        if ($result === false) {
            throw new QueryException($this->wpdb->last_error, $prepared);
        }

        return (int) $result;
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(): void
    {
        if ($this->transactionActive) {
            throw new TransactionException('A transaction is already active.');
        }

        $this->wpdb->query('START TRANSACTION');

        $this->transactionActive = true;
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): void
    {
        if (! $this->transactionActive) {
            throw new TransactionException('No active transaction to commit.');
        }

        $this->wpdb->query('COMMIT');

        $this->transactionActive = false;
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack(): void
    {
        if (! $this->transactionActive) {
            throw new TransactionException('No active transaction to roll back.');
        }

        $this->wpdb->query('ROLLBACK');

        $this->transactionActive = false;
    }

    /**
     * {@inheritDoc}
     */
    public function createQueryBuilder(): QueryBuilderInterface
    {
        return new DatabaseQueryBuilder($this);
    }

    /**
     * Prepare a raw SQL statement with the given positional parameters.
     *
     * @param array<int,mixed> $parameters
     */
    private function prepare(string $sql, array $parameters): string
    {
        if ($parameters === []) {
            return $sql;
        }

        return $this->wpdb->prepare($sql, $parameters);
    }

    /**
     * Resolve a logical table name to its prefixed physical name.
     *
     * Only findOne(), findAll(), insert(), update() and delete() apply
     * this resolution. query() and execute() accept raw SQL and expect
     * callers to have already fully qualified any table names.
     */
    private function resolveTable(string $table): string
    {
        if (str_starts_with($table, $this->wpdb->prefix)) {
            return $table;
        }

        return $this->wpdb->prefix . $table;
    }

    /**
     * Build a WHERE clause fragment and its bindings from a criteria array.
     *
     * @param array<string,mixed> $criteria
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildWhere(array $criteria): array
    {
        if ($criteria === []) {
            return ['', []];
        }

        $clauses = [];
        $bindings = [];

        foreach ($criteria as $column => $value) {
            $placeholder = (new QueryParameter($value))->placeholder();
            $clauses[] = sprintf('%s = %s', $this->quoteIdentifier($column), $placeholder);
            $bindings[] = $value;
        }

        return [' WHERE ' . implode(' AND ', $clauses), $bindings];
    }

    /**
     * Quote a column identifier so that MySQL reserved words (e.g. "order",
     * "read") can be used safely as column names.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
