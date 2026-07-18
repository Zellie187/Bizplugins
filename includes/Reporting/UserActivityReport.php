<?php

declare(strict_types=1);

namespace BizHub\Reporting;

use BizHub\Framework\Database\Contracts\DatabaseInterface;

/**
 * Aggregates user activity statistics from the audit trail.
 *
 * @package BizHub\Reporting
 */
final class UserActivityReport
{
    private const TABLE = 'bizhub_audit_log';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * Return the number of audited actions performed by a user.
     */
    public function actionCountForUser(int $userId): int
    {
        return count($this->database->findAll(self::TABLE, ['user_id' => $userId]));
    }

    /**
     * Return the most recently active users, ranked by action count.
     *
     * @return array<int,array{user_id:int,action_count:int}>
     */
    public function mostActiveUsers(int $limit = 10): array
    {
        $rows = $this->database->findAll(self::TABLE);

        $counts = [];

        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);

            if ($userId <= 0) {
                continue;
            }

            $counts[$userId] = ($counts[$userId] ?? 0) + 1;
        }

        arsort($counts);

        $result = [];

        foreach (array_slice($counts, 0, $limit, true) as $userId => $count) {
            $result[] = ['user_id' => $userId, 'action_count' => $count];
        }

        return $result;
    }

    /**
     * Return action counts grouped by action name.
     *
     * @return array<string,int>
     */
    public function countsByAction(): array
    {
        $rows = $this->database->findAll(self::TABLE);

        $counts = [];

        foreach ($rows as $row) {
            $action = $row['action'];
            $counts[$action] = ($counts[$action] ?? 0) + 1;
        }

        return $counts;
    }
}
