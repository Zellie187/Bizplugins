<?php

declare(strict_types=1);

namespace BizHub\Workflow\Notifications;

use BizHub\Framework\Events\Event;
use BizHub\Framework\Events\Listener;
use BizHub\Notifications\Notification;
use BizHub\Notifications\NotificationQueue;
use BizHub\Workflow\Events\WorkflowTransitioned;

/**
 * Notifies a workflow's creator when it reaches a status a workflow
 * type's notification template map (config/notifications.php) has an
 * entry for.
 *
 * Delivery itself is delegated entirely to BizHub's own
 * NotificationQueue/NotificationChannel infrastructure - this class
 * only decides *whether* and *what* to notify, satisfying
 * BH-WORKFLOW-SPEC-001 section 6's requirement that every workflow
 * define its notifications, without duplicating BizHub's delivery
 * mechanism.
 *
 * @package BizHub\Workflow\Notifications
 */
final class WorkflowNotificationListener implements Listener
{
    /**
     * @var array<string,array<string,array{subject:string,body:string}>>
     */
    private array $templates;

    public function __construct(
        private readonly NotificationQueue $notificationQueue
    ) {
        $this->templates = require BIZUPKEEP_WORKFLOW_CONFIG_PATH . 'notifications.php';
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Event $event): void
    {
        if (! $event instanceof WorkflowTransitioned) {
            return;
        }

        $template = $this->templates[$event->workflow->getWorkflowType()][$event->transition->action] ?? null;

        if ($template === null) {
            return;
        }

        $recipientId = $event->workflow->getCreatedBy();

        if ($recipientId <= 0) {
            return;
        }

        $this->notificationQueue->enqueue(new Notification(
            $recipientId,
            $this->render($template['subject'], $event),
            $this->render($template['body'], $event),
            ['in_app', 'email']
        ));
    }

    private function render(string $template, WorkflowTransitioned $event): string
    {
        $quoteAmount = $event->workflow->getMetadata()['quote_amount'] ?? null;

        return strtr($template, [
            '{workflow_uuid}' => $event->workflow->getUuid(),
            '{action}' => $event->transition->action,
            '{reason}' => $event->transition->reason,
            '{from_status}' => $event->transition->from?->label() ?? '',
            '{to_status}' => $event->transition->to->label(),
            // Only meaningful for Annual Return's request_payment/revise_quote
            // notifications - empty for every other workflow type/action,
            // since nothing else ever sets quote_amount in metadata.
            '{quote_amount}' => is_numeric($quoteAmount) ? number_format((float) $quoteAmount, 2) : '',
        ]);
    }
}
