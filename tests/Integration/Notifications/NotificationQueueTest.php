<?php

declare(strict_types=1);

namespace BizHub\Tests\Integration\Notifications;

use BizHub\ClientPortal\DTO\ClientData;
use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Repositories\ClientRepository;
use BizHub\ClientPortal\Services\ClientService;
use BizHub\ClientPortal\Services\NotificationService as PortalNotificationService;
use BizHub\Framework\Logging\Logger;
use BizHub\Framework\Logging\LogManager;
use BizHub\Framework\Support\Uuid;
use BizHub\Notifications\EmailNotification;
use BizHub\Notifications\InAppNotification;
use BizHub\Notifications\Notification;
use BizHub\Notifications\NotificationQueue;
use BizHub\Notifications\SMSNotification;
use BizHub\Tests\Mocks\InMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class NotificationQueueTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__bizhub_test_mail_sent'] = [];
        $GLOBALS['__bizhub_test_users'] = [
            77 => (object) ['user_email' => 'client77@example.com'],
        ];
    }

    public function test_processes_email_and_in_app_channels_successfully(): void
    {
        $db = new InMemoryDatabase();
        $clientRepository = new ClientRepository($db);
        $clientService = new ClientService($clientRepository);
        $portalNotifications = new PortalNotificationService($db);

        $clientUuid = Uuid::generate();
        $clientService->createClient(new ClientData($clientUuid, 77, new ProfileData('Ayesha', 'Patel'), ClientStatus::ACTIVE));

        $queue = new NotificationQueue($db);
        $queue->registerChannel(new EmailNotification());
        $queue->registerChannel(new SMSNotification(new Logger(new LogManager())));
        $queue->registerChannel(new InAppNotification($clientService, $portalNotifications));

        $queue->enqueue(new Notification(77, 'Your application was approved', 'Great news!', ['email', 'in_app']));
        $queue->enqueue(new Notification(77, 'SMS reminder', 'Reminder text', ['sms']));

        $delivered = $queue->processPending();

        $this->assertSame(1, $delivered);
        $this->assertCount(1, $GLOBALS['__bizhub_test_mail_sent']);
        $this->assertSame('client77@example.com', $GLOBALS['__bizhub_test_mail_sent'][0]['to']);

        $inbox = $portalNotifications->forClient($clientUuid);
        $this->assertCount(1, $inbox);
        $this->assertSame('Your application was approved', $inbox[0]->getTitle());

        $statuses = array_column($db->findAll('bizhub_notification_queue'), 'status');
        sort($statuses);
        $this->assertSame(['failed', 'sent'], $statuses);
    }
}
