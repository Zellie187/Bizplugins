<?php

declare(strict_types=1);

namespace BizHub\Notifications;

/**
 * Contract for notification delivery channels.
 *
 * @package BizHub\Notifications
 */
interface NotificationChannel
{
    /**
     * Return the channel's unique name (e.g. "email", "sms", "in_app").
     */
    public function name(): string;

    /**
     * Deliver a notification through this channel.
     *
     * Returns true on success, false on failure. Implementations should
     * not throw for expected delivery failures.
     */
    public function send(Notification $notification): bool;
}
