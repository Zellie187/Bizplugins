<?php

declare(strict_types=1);

namespace BizHub\Notifications\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Notifications\EmailNotification;
use BizHub\Notifications\InAppNotification;
use BizHub\Notifications\NotificationQueue;
use BizHub\Notifications\SMSNotification;

/**
 * Notifications Service Provider.
 *
 * Registers every delivery channel with the notification queue.
 *
 * @package BizHub\Notifications\Providers
 */
final class NotificationServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly NotificationQueue $queue,
        private readonly EmailNotification $emailChannel,
        private readonly SMSNotification $smsChannel,
        private readonly InAppNotification $inAppChannel
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        $this->queue->registerChannel($this->emailChannel);
        $this->queue->registerChannel($this->smsChannel);
        $this->queue->registerChannel($this->inAppChannel);
    }

    /**
     * Return the notification queue.
     */
    public function queue(): NotificationQueue
    {
        return $this->queue;
    }
}
