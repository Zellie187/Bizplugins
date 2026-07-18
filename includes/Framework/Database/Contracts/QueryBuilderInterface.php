<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Contracts;

/**
 * Defines a fluent query builder abstraction.
 *
 * Implementations are responsible for translating the fluent API
 * into the underlying database driver's query language.
 *
 * Repositories should prefer this interface over writing raw SQL.
 *
 * @package BizHub\Framework\Database\Contracts
 */
interface QueryBuilderInterface
{
    /**
     * Specify the table to query.
     *
     * @param string $table Table name.
     *
     * @return static
     */
    public function table(string $table): static;

    /**
     * Select columns.
     *
     * @param string ...$columns Columns to select.
     *
     * @return static
     */
    public function select(string ...$columns): static;

    /**
     * Add a where condition.
     *
     * @param string $column
     * @param mixed  $value
     * @param string $operator
     *
     * @return static
     */
    public function where(
        string $column,
        mixed $value,
        string $operator = '='
    ): static;

    /**
     * Add an OR where condition.
     *
     * @param string $column
     * @param mixed  $value
     * @param string $operator
     *
     * @return static
     */
    public function orWhere(
        string $column,
        mixed $value,
        string $operator = '='
    ): static;

    /**
     * Add an ORDER BY clause.
     *
     * @param string $column
     * @param string $direction ASC or DESC.
     *
     * @return static
     */
    public function orderBy(
        string $column,
        string $direction = 'ASC'
    ): static;

    /**
     * Set a result limit.
     *
     * @param int $limit
     *
     * @return static
     */
    public function limit(int $limit): static;

    /**
     * Set the result offset.
     *
     * @param int $offset
     *
     * @return static
     */
    public function offset(int $offset): static;

    /**
     * Execute the query and return all rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get(): array;

    /**
     * Execute the query and return the first row.
     *
     * @return array<string,mixed>|null
     */
    public function first(): ?array;

    /**
     * Count matching records.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Insert a record.
     *
     * @param array<string,mixed> $data
     *
     * @return int Inserted record ID.
     */
    public function insert(array $data): int;

    /**
     * Update matching records.
     *
     * @param array<string,mixed> $data
     *
     * @return int Number of affected rows.
     */
    public function update(array $data): int;

    /**
     * Delete matching records.
     *
     * @return int Number of affected rows.
     */
    public function delete(): int;

    /**
     * Reset the builder state.
     *
     * @return static
     */
    public function reset(): static;

    /**
     * Return the generated SQL.
     *
     * Primarily intended for debugging and logging.
     */
    public function toSql(): string;

    /**
     * Return the bound parameters.
     *
     * @return array<int,mixed>
     */
    public function getBindings(): array;
}
