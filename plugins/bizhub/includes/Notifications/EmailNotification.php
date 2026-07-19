<?php

declare(strict_types=1);

namespace BizHub\Notifications;

/**
 * Delivers notifications via WordPress email (wp_mail).
 *
 * @package BizHub\Notifications
 */
final class EmailNotification implements NotificationChannel
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'email';
    }

    /**
     * {@inheritDoc}
     */
    public function send(Notification $notification): bool
    {
        $user = get_userdata($notification->recipientId);

        if ($user === false || empty($user->user_email)) {
            return false;
        }

        return wp_mail($user->user_email, $notification->subject, $notification->body);
    }
}
