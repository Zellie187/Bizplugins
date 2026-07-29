<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

/**
 * Lifecycle of one imported bank statement row, before (Pending) or
 * after it becomes a real journal entry (Categorized) or is explicitly
 * excluded from ever becoming one (Ignored - internal transfers,
 * non-business lines).
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum StagedTransactionStatus: string
{
    case Pending = 'pending';
    case Categorized = 'categorized';
    case Ignored = 'ignored';
}
