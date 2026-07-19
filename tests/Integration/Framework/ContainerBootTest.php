<?php

declare(strict_types=1);

namespace BizHub\Tests\Integration\Framework;

use BizHub\Admin\Providers\AdminServiceProvider;
use BizHub\Api\ApiServiceProvider;
use BizHub\Applications\Providers\ApplicationServiceProvider;
use BizHub\ClientPortal\Providers\ClientServiceProvider;
use BizHub\Companies\Providers\CompanyServiceProvider;
use BizHub\Dashboard\Providers\DashboardServiceProvider;
use BizHub\Documents\Providers\DocumentServiceProvider;
use BizHub\Framework\Bootstrap\Constants;
use BizHub\Framework\Container\ContainerFactory;
use BizHub\Framework\Database\Providers\DatabaseServiceProvider;
use BizHub\Framework\Events\EventServiceProvider;
use BizHub\Framework\Install\Providers\InstallServiceProvider;
use BizHub\Integrations\Forminator\ServiceProvider as ForminatorServiceProvider;
use BizHub\Integrations\WooCommerce\ServiceProvider as WooCommerceServiceProvider;
use BizHub\Notifications\Providers\NotificationServiceProvider;
use BizHub\Reporting\Providers\ReportingServiceProvider;
use BizHub\Security\Auth\Providers\AuthServiceProvider;
use BizHub\Security\Authorization\Providers\AuthorizationServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Resolves every provider that BizHub\Framework\Bootstrap\Application
 * registers, through the real DI container (not test doubles). This is
 * the only test that exercises the production container-wiring path;
 * it exists because a missing interface binding (AuthorizationServiceInterface
 * was never bound to a concrete class) reached production undetected,
 * as every other test constructs services by hand instead of through
 * the container.
 *
 * Keep the provider list below in sync with
 * Application::registerProviders().
 */
final class ContainerBootTest extends TestCase
{
    protected function setUp(): void
    {
        if (! \defined('BIZHUB_PLUGIN_PATH')) {
            define('BIZHUB_PLUGIN_PATH', sys_get_temp_dir() . '/bizhub_container_boot_test/');
        }

        Constants::register();

        $GLOBALS['wpdb'] = new \wpdb();
    }

    /**
     * @return array<string,array{0:class-string}>
     */
    public static function providerClassesProvider(): array
    {
        return [
            DatabaseServiceProvider::class => [DatabaseServiceProvider::class],
            InstallServiceProvider::class => [InstallServiceProvider::class],
            EventServiceProvider::class => [EventServiceProvider::class],
            AuthServiceProvider::class => [AuthServiceProvider::class],
            AuthorizationServiceProvider::class => [AuthorizationServiceProvider::class],
            CompanyServiceProvider::class => [CompanyServiceProvider::class],
            ClientServiceProvider::class => [ClientServiceProvider::class],
            ApplicationServiceProvider::class => [ApplicationServiceProvider::class],
            DocumentServiceProvider::class => [DocumentServiceProvider::class],
            WooCommerceServiceProvider::class => [WooCommerceServiceProvider::class],
            ForminatorServiceProvider::class => [ForminatorServiceProvider::class],
            DashboardServiceProvider::class => [DashboardServiceProvider::class],
            NotificationServiceProvider::class => [NotificationServiceProvider::class],
            ApiServiceProvider::class => [ApiServiceProvider::class],
            AdminServiceProvider::class => [AdminServiceProvider::class],
            ReportingServiceProvider::class => [ReportingServiceProvider::class],
        ];
    }

    #[DataProvider('providerClassesProvider')]
    public function test_provider_resolves_through_the_real_container(string $providerClass): void
    {
        $container = ContainerFactory::create();

        $this->assertInstanceOf($providerClass, $container->get($providerClass));
    }
}
