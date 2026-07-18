<?php

declare(strict_types=1);

namespace BizHub\Tests\Integration\ClientPortal;

use BizHub\ClientPortal\DTO\ClientData;
use BizHub\ClientPortal\DTO\NotificationData;
use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use BizHub\ClientPortal\Repositories\ClientRepository;
use BizHub\ClientPortal\Services\ClientService;
use BizHub\ClientPortal\Services\NotificationService;
use BizHub\ClientPortal\Services\ProfileService;
use BizHub\Framework\Support\Uuid;
use BizHub\Tests\Mocks\InMemoryDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClientServiceTest extends TestCase
{
    private ClientService $clientService;
    private ProfileService $profileService;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        $db = new InMemoryDatabase();
        $repository = new ClientRepository($db);
        $this->clientService = new ClientService($repository);
        $this->profileService = new ProfileService($repository);
        $this->notificationService = new NotificationService($db);
    }

    public function test_create_and_fetch_by_uuid_and_wp_user(): void
    {
        $uuid = Uuid::generate();
        $this->clientService->createClient(new ClientData($uuid, 501, new ProfileData('Sipho', 'Nkosi'), ClientStatus::ACTIVE));

        $this->assertSame('Sipho Nkosi', $this->clientService->getClient($uuid)->getProfile()->getFullName());
        $this->assertSame($uuid, $this->clientService->getClientByWpUserId(501)->getUuid());
    }

    public function test_duplicate_wp_user_rejected(): void
    {
        $this->clientService->createClient(new ClientData(Uuid::generate(), 501, new ProfileData('A', 'B'), ClientStatus::ACTIVE));

        $this->expectException(InvalidArgumentException::class);

        $this->clientService->createClient(new ClientData(Uuid::generate(), 501, new ProfileData('C', 'D'), ClientStatus::ACTIVE));
    }

    public function test_profile_update(): void
    {
        $uuid = Uuid::generate();
        $this->clientService->createClient(new ClientData($uuid, 501, new ProfileData('Sipho', 'Nkosi'), ClientStatus::ACTIVE));

        $this->profileService->updateProfile($uuid, new ProfileData('Sipho', 'Dlamini', '0827654321'));

        $this->assertSame('Dlamini', $this->clientService->getClient($uuid)->getProfile()->getLastName());
    }

    public function test_status_update(): void
    {
        $uuid = Uuid::generate();
        $this->clientService->createClient(new ClientData($uuid, 501, new ProfileData('Sipho', 'Nkosi'), ClientStatus::ACTIVE));

        $this->clientService->updateStatus($uuid, ClientStatus::SUSPENDED);

        $this->assertSame(ClientStatus::SUSPENDED, $this->clientService->getClient($uuid)->getStatus());
    }

    public function test_get_missing_client_throws(): void
    {
        $this->expectException(ClientNotFoundException::class);

        $this->clientService->getClient(Uuid::generate());
    }

    public function test_notification_inbox(): void
    {
        $uuid = Uuid::generate();
        $this->clientService->createClient(new ClientData($uuid, 501, new ProfileData('Sipho', 'Nkosi'), ClientStatus::ACTIVE));

        $this->notificationService->send(new NotificationData($uuid, 'Welcome', 'Welcome to BizHub'));
        $this->notificationService->send(new NotificationData($uuid, 'Reminder', 'Annual return due', 'warning'));

        $this->assertSame(2, $this->notificationService->unreadCount($uuid));

        $items = $this->notificationService->forClient($uuid);
        $this->notificationService->markAsRead($items[0]->getUuid());

        $this->assertSame(1, $this->notificationService->unreadCount($uuid));
    }

    public function test_delete_client(): void
    {
        $uuid = Uuid::generate();
        $this->clientService->createClient(new ClientData($uuid, 501, new ProfileData('Sipho', 'Nkosi'), ClientStatus::ACTIVE));

        $this->clientService->deleteClient($uuid);

        $this->expectException(ClientNotFoundException::class);
        $this->clientService->getClient($uuid);
    }
}
