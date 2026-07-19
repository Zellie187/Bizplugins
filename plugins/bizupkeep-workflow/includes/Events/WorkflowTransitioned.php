<?php

declare(strict_types=1);

namespace BizHub\Workflow\Events;

use BizHub\Framework\Events\Event;
use BizHub\Workflow\DTO\Transition;
use BizHub\Workflow\Entities\WorkflowInstance;

/**
 * Raised after a workflow instance successfully moves from one status
 * to another.
 *
 * @package BizHub\Workflow\Events
 */
final class WorkflowTransitioned extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow,
        public readonly Transition $transition
    ) {
        parent::__construct();
    }
}
