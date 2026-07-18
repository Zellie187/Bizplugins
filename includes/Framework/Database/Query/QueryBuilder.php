<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Query;

use InvalidArgumentException;

/**
 * Class QueryBuilder
 *
 * Lightweight SQL query builder for the BizHub Framework.
 *
 * Supports:
 * - SELECT
 * - INSERT
 * - UPDATE
 * - DELETE
 * - WHERE
 * - JOIN
 * - ORDER BY
 * - GROUP BY
 * - LIMIT
 * - OFFSET
 *
 * Produces prepared-statement compatible SQL and parameter bindings.
 *
 * @package BizHub\Framework\Database\Query
 */
final class QueryBuilder
{
    /**
     * Query type.
     */
    private string $type = '';

    /**
     * Table name.
     */
    private string $table = '';

    /**
     * Selected columns.
     *
     * @var array<int,string>
     */
    private array $columns = ['*'];

    /**
     * Insert/update values.
     *
     * @var array<string,mixed>
     */
    private array $values = [];

    /**
     * WHERE clauses.
     *
     * @var array<int,string>
     */
    private array $where = [];

    /**
     * JOIN clauses.
     *
     * @var array<int,string>
     */
    private array $joins = [];

    /**
     * GROUP BY columns.
     *
     * @var array<int,string>
     */
    private array $groupBy = [];

    /**
     * ORDER BY clauses.
     *
     * @var array<int,string>
     */
    private array $orderBy = [];

    /**
     * Query bindings.
     *
     * @var array<string,mixed>
     */
    private array $bindings = [];

    /**
     * LIMIT.
     */
    private ?int $limit = null;

    /**
     * OFFSET.
     */
    private ?int $offset = null;

    /**
     * SELECT query.
     *
     * @param array<int,string> $columns
     */
    public function select(array $columns = ['*']): self
    {
        $this->reset();

        $this->type = 'select';
        $this->columns = $columns;

        return $this;
    }

    /**
     * INSERT query.
     *
     * @param array<string,mixed> $values
     */
    public function insert(string $table, array $values): self
    {
        $this->reset();

        $this->type = 'insert';
        $this->table = $table;
        $this->values = $values;
        $this->bindings = $values;

        return $this;
    }

    /**
     * UPDATE query.
     */
    public function update(string $table): self
    {
        $this->reset();

        $this->type = 'update';
        $this->table = $table;

        return $this;
    }

    /**
     * DELETE query.
     */
    public function delete(): self
    {
        $this->reset();

        $this->type = 'delete';

        return $this;
    }

    /**
     * FROM table.
     */
    public function from(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * SET values.
     *
     * @param array<string,mixed> $values
     */
    public function set(array $values): self
    {
        foreach ($values as $column => $value) {
            $this->values[$column] = $value;
            $this->bindings[$column] = $value;
        }

        return $this;
    }

    /**
     * WHERE clause.
     *
     * Example:
     * where('id','=','id',5)
     */
    public function where(
        string $column,
        string $operator,
        string $parameter,
        mixed $value
    ): self {
        $placeholder = ':' . $parameter;

        $this->where[] = sprintf(
            '%s %s %s',
            $column,
            $operator,
            $placeholder
        );

        $this->bindings[$parameter] = $value;

        return $this;
    }

    /**
     * JOIN clause.
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER'
    ): self {
        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            strtoupper($type),
            $table,
            $first,
            $operator,
            $second
        );

        return $this;
    }

    /**
     * GROUP BY.
     */
    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groupBy[] = $column;
        }

        return $this;
    }

    /**
     * ORDER BY.
     */
    public function orderBy(
        string $column,
        string $direction = 'ASC'
    ): self {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException(
                'Order direction must be ASC or DESC.'
            );
        }

        $this->orderBy[] = $column . ' ' . $direction;

        return $this;
    }

    /**
     * LIMIT.
     */
    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    /**
     * OFFSET.
     */
    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /**
     * Build SQL.
     */
    public function toSql(): string
    {
        return match ($this->type) {
            'select' => $this->buildSelect(),
            'insert' => $this->buildInsert(),
            'update' => $this->buildUpdate(),
            'delete' => $this->buildDelete(),
            default => throw new InvalidArgumentException(
                'No query type specified.'
            ),
        };
    }

    /**
     * Bindings.
     *
     * @return array<string,mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * SELECT SQL.
     */
    private function buildSelect(): string
    {
        $sql = sprintf(
            'SELECT %s FROM %s',
            implode(', ', $this->columns),
            $this->table
        );

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if ($this->where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if ($this->groupBy !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if ($this->orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * INSERT SQL.
     */
    private function buildInsert(): string
    {
        $columns = array_keys($this->values);

        $placeholders = array_map(
            static fn(string $column): string => ':' . $column,
            $columns
        );

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
    }

    /**
     * UPDATE SQL.
     */
    private function buildUpdate(): string
    {
        $sets = [];

        foreach (array_keys($this->values) as $column) {
            $sets[] = sprintf(
                '%s = :%s',
                $column,
                $column
            );
        }

        $sql = sprintf(
            'UPDATE %s SET %s',
            $this->table,
            implode(', ', $sets)
        );

        if ($this->where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        return $sql;
    }

    /**
     * DELETE SQL.
     */
    private function buildDelete(): string
    {
        $sql = sprintf(
            'DELETE FROM %s',
            $this->table
        );

        if ($this->where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        return $sql;
    }

    /**
     * Reset builder state.
     */
    private function reset(): void
    {
        $this->type = '';
        $this->table = '';
        $this->columns = ['*'];
        $this->values = [];
        $this->where = [];
        $this->joins = [];
        $this->groupBy = [];
        $this->orderBy = [];
        $this->bindings = [];
        $this->limit = null;
        $this->offset = null;
    }
}
