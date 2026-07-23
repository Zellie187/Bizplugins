<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin;

/**
 * The single source of truth for "how many days without an update
 * makes a non-terminal workflow overdue" - shared by every admin
 * screen that flags stalled applications
 * (WorkflowDashboardPage/WorkflowAdminMenu/WorkflowBoardPage), so the
 * threshold can never drift between them the way three separately
 * duplicated `private const OVERDUE_DAYS = 7` did before this class
 * existed.
 *
 * @package BizHub\Workflow\Admin
 */
final class OverdueThreshold
{
    /**
     * Deliberately generous - a business-day-scale heuristic, not a
     * strict SLA - so staff can spot applications that have stalled
     * without every legitimately slow filing (e.g. awaiting a client's
     * documents) being flagged.
     */
    public const DAYS = 7;
}
