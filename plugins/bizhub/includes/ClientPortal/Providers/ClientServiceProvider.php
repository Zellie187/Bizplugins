<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Providers;

use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\Services\NotificationService;
use BizHub\ClientPortal\Services\ProfileService;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * ClientPortal Service Provider.
 *
 * Exposes the ClientPortal module's services to the rest of the
 * application. Bindings are declared in ClientPortal/definitions.php.
 *
 * @package BizHub\ClientPortal\Providers
 */
final class ClientServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly ClientServiceInterface $clientService,
        private readonly ProfileService $profileService,
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
        // Bindings are declared in ClientPortal/definitions.php.
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
    }

    /**
     * Return the Client service.
     */
    public function clientService(): ClientServiceInterface
    {
        return $this->clientService;
    }

    /**
     * Return the Profile service.
     */
    public function profileService(): ProfileService
    {
        return $this->profileService;
    }

    /**
     * Return the Notification service.
     */
    public function notificationService(): NotificationService
    {
        return $this->notificationService;
    }
}
