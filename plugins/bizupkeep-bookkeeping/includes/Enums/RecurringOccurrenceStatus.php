<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

/**
 * Lifecycle of one cron-generated recurring occurrence, before (Pending)
 * or after it becomes a real journal entry (Posted) or is explicitly
 * skipped for this due date without ever posting (Skipped).
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum RecurringOccurrenceStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Skipped = 'skipped';
}
