<?php

declare(strict_types=1);

namespace BizHub\Workflow\Events;

use BizHub\Framework\Events\Event;
use BizHub\Workflow\Entities\WorkflowInstance;

/**
 * Raised when a workflow instance reaches a successful terminal
 * status (Completed or Archived).
 *
 * @package BizHub\Workflow\Events
 */
final class WorkflowCompleted extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow
    ) {
        parent::__construct();
    }
}
