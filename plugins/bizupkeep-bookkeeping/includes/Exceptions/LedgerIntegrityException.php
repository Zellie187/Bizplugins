<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Exceptions;

/**
 * Raised when a computed report (e.g. Trial Balance) fails to net to
 * zero. Should be structurally impossible given LedgerService's
 * balance gate on every post - this exists purely as the cheapest
 * possible correctness tripwire, since a silent accounting error is
 * far worse than a loud one.
 *
 * @package BizHub\Bookkeeping\Exceptions
 */
final class LedgerIntegrityException extends BookkeepingException
{
}
