<?php

declare(strict_types=1);

namespace BizHub\Tests\Mocks;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Database\Contracts\QueryBuilderInterface;
use BizHub\Framework\Database\Query\DatabaseQueryBuilder;

/**
 * In-memory DatabaseInterface implementation for tests.
 *
 * Not a full SQL engine: findAll() only supports equality criteria and
 * single-column ordering, which is sufficient for every repository in
 * this codebase.
 */
final class InMemoryDatabase implements DatabaseInterface
{
    /** @var array<string,array<int,array<string,mixed>>> */
    private array $tables = [];

    private int $nextId = 1;

    /**
     * Seed a table with rows, bypassing insert()'s auto-increment id.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function seed(string $table, array $rows): void
    {
        $this->tables[$table] = $rows;
    }

    public function findOne(string $table, array $criteria): ?array
    {
        $rows = $this->findAll($table, $criteria, [], 1);

        return $rows[0] ?? null;
    }

    public function findAll(
        string $table,
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): array {
        $rows = $this->tables[$table] ?? [];

        $rows = array_values(array_filter($rows, function (array $row) use ($criteria): bool {
            foreach ($criteria as $column => $value) {
                if (($row[$column] ?? null) != $value) {
                    return false;
                }
            }

            return true;
        }));

        foreach ($orderBy as $column => $direction) {
            usort(
                $rows,
                static fn (array $a, array $b): int =>
                    (($a[$column] ?? 0) <=> ($b[$column] ?? 0)) * (strtoupper($direction) === 'DESC' ? -1 : 1)
            );
        }

        if ($limit !== null) {
            $rows = array_slice($rows, $offset, $limit);
        }

        return $rows;
    }

    public function insert(string $table, array $data): int
    {
        $data['id'] = $this->nextId++;
        $this->tables[$table][] = $data;

        return $data['id'];
    }

    public function update(string $table, array $data, array $criteria): int
    {
        $count = 0;

        foreach ($this->tables[$table] ?? [] as $i => $row) {
            $match = true;

            foreach ($criteria as $column => $value) {
                if (($row[$column] ?? null) != $value) {
                    $match = false;

                    break;
                }
            }

            if ($match) {
                $this->tables[$table][$i] = array_merge($row, $data);
                $count++;
            }
        }

        return $count;
    }

    public function delete(string $table, array $criteria): int
    {
        $count = 0;

        $this->tables[$table] = array_values(array_filter(
            $this->tables[$table] ?? [],
            function (array $row) use ($criteria, &$count): bool {
                foreach ($criteria as $column => $value) {
                    if (($row[$column] ?? null) != $value) {
                        return true;
                    }
                }

                $count++;

                return false;
            }
        ));

        return $count;
    }

    public function exists(string $table, array $criteria): bool
    {
        return $this->findOne($table, $criteria) !== null;
    }

    public function query(string $sql, array $parameters = []): array
    {
        return [];
    }

    public function execute(string $sql, array $parameters = []): int
    {
        return 0;
    }

    public function beginTransaction(): void
    {
    }

    public function commit(): void
    {
    }

    public function rollBack(): void
    {
    }

    public function createQueryBuilder(): QueryBuilderInterface
    {
        return new DatabaseQueryBuilder($this);
    }
}
