<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Query;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Database\Contracts\QueryBuilderInterface;
use BizHub\Framework\Database\ValueObjects\QueryCondition;
use BizHub\Framework\Database\ValueObjects\QueryOrder;
use InvalidArgumentException;

/**
 * Fluent query builder backed by a DatabaseInterface connection.
 *
 * @package BizHub\Framework\Database\Query
 */
final class DatabaseQueryBuilder implements QueryBuilderInterface
{
    private string $table = '';

    /**
     * @var array<int,string>
     */
    private array $columns = ['*'];

    /**
     * @var array<int,QueryCondition>
     */
    private array $conditions = [];

    /**
     * @var array<int,QueryOrder>
     */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function table(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function select(string ...$columns): static
    {
        $this->columns = $columns === [] ? ['*'] : $columns;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function where(
        string $column,
        mixed $value,
        string $operator = '='
    ): static {
        $this->conditions[] = new QueryCondition($column, $operator, $value, 'AND');

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function orWhere(
        string $column,
        mixed $value,
        string $operator = '='
    ): static {
        $this->conditions[] = new QueryCondition($column, $operator, $value, 'OR');

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function orderBy(
        string $column,
        string $direction = 'ASC'
    ): static {
        $this->orders[] = new QueryOrder($column, $direction);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function limit(int $limit): static
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function offset(int $offset): static
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function get(): array
    {
        return $this->database->query($this->toSql(), $this->getBindings());
    }

    /**
     * {@inheritDoc}
     */
    public function first(): ?array
    {
        $rows = (clone $this)->limit(1)->get();

        return $rows[0] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) AS aggregate FROM %s%s',
            $this->table,
            $this->buildWhere()
        );

        $rows = $this->database->query($sql, $this->getBindings());

        return (int) ($rows[0]['aggregate'] ?? 0);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(array $data): int
    {
        return $this->database->insert($this->table, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $data): int
    {
        return $this->database->update($this->table, $data, $this->criteriaArray());
    }

    /**
     * {@inheritDoc}
     */
    public function delete(): int
    {
        return $this->database->delete($this->table, $this->criteriaArray());
    }

    /**
     * {@inheritDoc}
     */
    public function reset(): static
    {
        $this->table = '';
        $this->columns = ['*'];
        $this->conditions = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function toSql(): string
    {
        if ($this->table === '') {
            throw new InvalidArgumentException('No table specified for query.');
        }

        $sql = sprintf(
            'SELECT %s FROM %s',
            implode(', ', $this->columns),
            $this->table
        );

        $sql .= $this->buildWhere();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(
                ', ',
                array_map(
                    static fn (QueryOrder $order): string => $order->toSql(),
                    $this->orders
                )
            );
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
     * {@inheritDoc}
     *
     * @return array<int,mixed>
     */
    public function getBindings(): array
    {
        return array_map(
            static fn (QueryCondition $condition): mixed => $condition->value,
            $this->conditions
        );
    }

    /**
     * Build the WHERE clause fragment for the current conditions.
     */
    private function buildWhere(): string
    {
        if ($this->conditions === []) {
            return '';
        }

        $sql = '';

        foreach ($this->conditions as $index => $condition) {
            $sql .= $index === 0
                ? ' WHERE '
                : ' ' . $condition->boolean . ' ';

            $sql .= $condition->toSql('%s');
        }

        return $sql;
    }

    /**
     * Reduce the current WHERE conditions to a simple criteria array,
     * used by repositories that only need equality matching.
     *
     * @return array<string,mixed>
     */
    private function criteriaArray(): array
    {
        $criteria = [];

        foreach ($this->conditions as $condition) {
            $criteria[$condition->column] = $condition->value;
        }

        return $criteria;
    }
}
