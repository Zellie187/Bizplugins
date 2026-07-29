<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Billing;

use BizHub\Bookkeeping\Billing\SubscriptionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionReminderService;
use BizHub\Bookkeeping\Billing\SubscriptionService;
use BizHub\Bookkeeping\Tests\Mocks\FakeClientRepository;
use BizHub\Bookkeeping\Tests\Mocks\FakeCompanyService;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Entities\Profile;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Entities\RegisteredAddress;
use BizHub\Notifications\NotificationQueue;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SubscriptionReminderServiceTest extends TestCase
{
    private const COMPANY = 'company-1';
    private const CLIENT_ID = 42;
    private const WP_USER_ID = 7;

    private InMemoryDatabase $database;
    private SubscriptionService $subscriptionService;
    private FakeCompanyService $companies;
    private NotificationQueue $notificationQueue;
    private SubscriptionReminderService $reminders;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();

        $subscriptionRepository = new SubscriptionRepository($this->database);
        $this->subscriptionService = new SubscriptionService($subscriptionRepository);

        $company = new Company(
            self::COMPANY,
            self::CLIENT_ID,
            '2026/123456/07',
            'Test Company (Pty) Ltd',
            'Private Company',
            CompanyStatus::ACTIVE,
            new RegisteredAddress('1 Main Road', '', 'Sandton', 'Johannesburg', 'Gauteng', '2196')
        );
        $this->companies = new FakeCompanyService([self::COMPANY => $company]);

        $client = new Client(
            'client-uuid-1',
            self::WP_USER_ID,
            new Profile('Test', 'Client'),
            ClientStatus::ACTIVE,
            createdAt: new DateTimeImmutable(),
            id: self::CLIENT_ID
        );
        $clients = new FakeClientRepository([self::CLIENT_ID => $client]);

        $this->notificationQueue = new NotificationQueue($this->database);

        $this->reminders = new SubscriptionReminderService(
            $subscriptionRepository,
            $this->subscriptionService,
            $this->companies,
            $clients,
            $this->notificationQueue
        );
    }

    private function queuedNotificationCount(): int
    {
        return count($this->database->all('bizhub_notification_queue'));
    }

    public function testNeverSubscribedCompanyGetsNoReminder(): void
    {
        $this->reminders->sendDueReminders();

        self::assertSame(0, $this->queuedNotificationCount());
    }

    public function testExpiringSoonReminderIsSentOnceWithinTheWindow(): void
    {
        $this->subscriptionService->extend(self::COMPANY, 2);

        $this->reminders->sendDueReminders();
        self::assertSame(1, $this->queuedNotificationCount());

        // Running the job again the same day must not send a duplicate.
        $this->reminders->sendDueReminders();
        self::assertSame(1, $this->queuedNotificationCount());
    }

    public function testNoReminderWhenFarFromExpiry(): void
    {
        $this->subscriptionService->extend(self::COMPANY, 30);

        $this->reminders->sendDueReminders();

        self::assertSame(0, $this->queuedNotificationCount());
    }

    public function testLapsedReminderIsSentOnce(): void
    {
        // Land paid_until in the past.
        $this->subscriptionService->extend(self::COMPANY, -10);

        $this->reminders->sendDueReminders();
        self::assertSame(1, $this->queuedNotificationCount());

        $this->reminders->sendDueReminders();
        self::assertSame(1, $this->queuedNotificationCount());
    }

    public function testRenewalBetweenTwoRunsReopensEligibility(): void
    {
        $this->subscriptionService->extend(self::COMPANY, -10);
        $this->reminders->sendDueReminders();
        self::assertSame(1, $this->queuedNotificationCount());

        // A fresh renewal lands paid_until close to expiry again -
        // eligible for a NEW expiring_soon reminder even though a
        // lapsed reminder already fired for the old paid_until.
        $this->subscriptionService->extend(self::COMPANY, 2);
        $this->reminders->sendDueReminders();

        self::assertSame(2, $this->queuedNotificationCount());
    }

    public function testSuspendedCompanyNeverGetsAReminder(): void
    {
        $this->subscriptionService->extend(self::COMPANY, -10);
        $this->subscriptionService->suspend(self::COMPANY);

        $this->reminders->sendDueReminders();

        self::assertSame(0, $this->queuedNotificationCount());
    }

    public function testUnresolvableCompanyDoesNotBreakTheRestOfTheBatch(): void
    {
        $this->subscriptionService->extend('company-unknown', 2);
        $this->subscriptionService->extend(self::COMPANY, 2);

        $this->reminders->sendDueReminders();

        // company-unknown has no matching FakeCompanyService entry and
        // is silently skipped; self::COMPANY still gets its reminder.
        self::assertSame(1, $this->queuedNotificationCount());
    }

    public function testQueuedNotificationIsActuallyProcessedNotLeftPending(): void
    {
        $this->subscriptionService->extend(self::COMPANY, 2);

        $this->reminders->sendDueReminders();

        // No delivery channel is registered on this test's bare
        // NotificationQueue (that only happens at real WP runtime, via
        // BizHub core's own NotificationServiceProvider), so the row
        // ends up 'failed' rather than 'sent' - the point here is
        // confirming sendDueReminders() actually calls processPending()
        // itself (nothing else in this codebase drains the queue on a
        // schedule), not that delivery genuinely succeeded.
        $rows = $this->database->all('bizhub_notification_queue');
        self::assertCount(1, $rows);
        self::assertNotSame('pending', $rows[0]['status']);
    }
}
