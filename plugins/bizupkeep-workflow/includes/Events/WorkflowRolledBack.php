<?php

declare(strict_types=1);

namespace BizHub\Workflow\Events;

use BizHub\Framework\Events\Event;
use BizHub\Workflow\Entities\WorkflowInstance;

/**
 * Raised when a workflow instance is rolled back to its previous
 * status.
 *
 * @package BizHub\Workflow\Events
 */
final class WorkflowRolledBack extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow,
        public readonly string $reason
    ) {
        parent::__construct();
    }
}
