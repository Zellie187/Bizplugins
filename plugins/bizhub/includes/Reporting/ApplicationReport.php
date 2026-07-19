<?php

declare(strict_types=1);

namespace BizHub\Reporting;

use BizHub\Applications\Entities\ApplicationStatus;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * Aggregates application statistics for reporting.
 *
 * @package BizHub\Reporting
 */
final class ApplicationReport
{
    private const TABLE = 'bizhub_applications';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * Return the total number of applications.
     */
    public function total(): int
    {
        return count($this->database->findAll(self::TABLE));
    }

    /**
     * Return application counts grouped by status.
     *
     * @return array<string,int>
     */
    public function countsByStatus(): array
    {
        $counts = [];

        foreach (ApplicationStatus::cases() as $status) {
            $counts[$status->value] = count(
                $this->database->findAll(self::TABLE, ['status' => $status->value])
            );
        }

        return $counts;
    }

    /**
     * Return application counts grouped by type.
     *
     * @return array<string,int>
     */
    public function countsByType(): array
    {
        $rows = $this->database->findAll(self::TABLE);

        $counts = [];

        foreach ($rows as $row) {
            $type = $row['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Return the average time, in days, applications take to reach a
     * final status (approved, rejected, cancelled, completed).
     */
    public function averageResolutionDays(): float
    {
        $rows = array_filter(
            $this->database->findAll(self::TABLE),
            static fn (array $row): bool => ! empty($row['submitted_at']) && ! empty($row['updated_at'])
        );

        if ($rows === []) {
            return 0.0;
        }

        $totalDays = 0;

        foreach ($rows as $row) {
            $submitted = new DateTimeImmutable((string) $row['submitted_at']);
            $updated = new DateTimeImmutable((string) $row['updated_at']);

            $totalDays += (int) $submitted->diff($updated)->format('%a');
        }

        return round($totalDays / count($rows), 1);
    }
}
