<?php

declare(strict_types=1);

use BizHub\Workflow\Events\WorkflowCancelled;
use BizHub\Workflow\Events\WorkflowCompleted;
use BizHub\Workflow\Events\WorkflowCreated;
use BizHub\Workflow\Events\WorkflowRolledBack;
use BizHub\Workflow\Events\WorkflowTransitioned;

/**
 * Reference list of every event BizUpKeep Workflow raises on BizHub's
 * shared EventDispatcher (BH-WORKFLOW-SPEC-001 section 8).
 *
 * Listener registration itself happens in each event's owning Service
 * Provider (e.g. WorkflowServiceProvider registers
 * WorkflowNotificationListener against WorkflowTransitioned) - this
 * file exists so every event this plugin can raise is documented and
 * discoverable in one place, for integrators listening from outside
 * this plugin.
 *
 * @return array<class-string,string>
 */
return [

    WorkflowCreated::class => 'A new workflow instance was started.',

    WorkflowTransitioned::class => 'A workflow instance moved from one status to another.',

    WorkflowCompleted::class => 'A workflow instance reached a successful terminal status.',

    WorkflowCancelled::class => 'A workflow instance was cancelled or rejected.',

    WorkflowRolledBack::class => 'A workflow instance was rolled back to its previous status.',

];
