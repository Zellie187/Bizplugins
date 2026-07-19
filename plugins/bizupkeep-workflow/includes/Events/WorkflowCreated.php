<?php

declare(strict_types=1);

namespace BizHub\Workflow\Events;

use BizHub\Framework\Events\Event;
use BizHub\Workflow\Entities\WorkflowInstance;

/**
 * Raised immediately after a new workflow instance is persisted.
 *
 * @package BizHub\Workflow\Events
 */
final class WorkflowCreated extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow
    ) {
        parent::__construct();
    }
}
