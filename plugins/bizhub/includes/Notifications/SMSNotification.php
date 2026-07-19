<?php

declare(strict_types=1);

namespace BizHub\Notifications;

use BizHub\Framework\Logging\Logger;

/**
 * Delivers notifications via SMS.
 *
 * No SMS gateway is currently integrated. This channel logs the
 * attempt and returns false until a provider (e.g. Twilio, Clickatell)
 * is wired in via a future update to send().
 *
 * @package BizHub\Notifications
 */
final class SMSNotification implements NotificationChannel
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'sms';
    }

    /**
     * {@inheritDoc}
     */
    public function send(Notification $notification): bool
    {
        $this->logger->warning(
            'SMS channel has no gateway configured; message not sent.',
            ['recipient_id' => $notification->recipientId, 'subject' => $notification->subject]
        );

        return false;
    }
}
