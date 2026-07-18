<?php

declare(strict_types=1);

namespace BizHub\Reporting;

use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Framework\Database\Contracts\DatabaseInterface;

/**
 * Aggregates company statistics for reporting.
 *
 * @package BizHub\Reporting
 */
final class CompanyReport
{
    private const TABLE = 'bizhub_companies';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * Return the total number of companies.
     */
    public function total(): int
    {
        return count($this->database->findAll(self::TABLE));
    }

    /**
     * Return company counts grouped by status.
     *
     * @return array<string,int>
     */
    public function countsByStatus(): array
    {
        $counts = [];

        foreach (CompanyStatus::cases() as $status) {
            $counts[$status->value] = count(
                $this->database->findAll(self::TABLE, ['status' => $status->value])
            );
        }

        return $counts;
    }

    /**
     * Return companies registered within a date range.
     *
     * @return array<int,array<string,mixed>>
     */
    public function registeredBetween(string $from, string $to): array
    {
        return array_values(array_filter(
            $this->database->findAll(self::TABLE),
            static fn (array $row): bool =>
                ($row['incorporation_date'] ?? null) !== null
                && $row['incorporation_date'] >= $from
                && $row['incorporation_date'] <= $to
        ));
    }
}
