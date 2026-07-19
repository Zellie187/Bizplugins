<?php

declare(strict_types=1);

namespace BizHub\Workflow\Events;

use BizHub\Framework\Events\Event;
use BizHub\Workflow\Entities\WorkflowInstance;

/**
 * Raised when a workflow instance is cancelled or rejected.
 *
 * @package BizHub\Workflow\Events
 */
final class WorkflowCancelled extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow,
        public readonly string $reason
    ) {
        parent::__construct();
    }
}
