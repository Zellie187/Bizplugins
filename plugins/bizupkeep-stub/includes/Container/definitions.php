<?php

declare(strict_types=1);

use BizHub\Stub\Contracts\StubApiClientInterface;
use BizHub\Stub\Contracts\StubAuthTokenServiceInterface;
use BizHub\Stub\Contracts\StubBusinessProvisionerInterface;
use BizHub\Stub\Contracts\StubBusinessRepositoryInterface;
use BizHub\Stub\Contracts\StubMigrationServiceInterface;
use BizHub\Stub\Http\StubApiClient;
use BizHub\Stub\Repositories\StubBusinessRepository;
use BizHub\Stub\Services\StubAuthTokenService;
use BizHub\Stub\Services\StubBusinessProvisioner;
use BizHub\Stub\Services\StubMigrationService;

/*
 * Contributed into BizHub's shared container via the
 * 'bizhub/container_definitions' filter - see bizupkeep-stub.php.
 * Concrete classes (admin pages, controllers, etc.) do not need
 * entries here: PHP-DI autowires them automatically. Only
 * interface -> concrete bindings need to be declared explicitly.
 */
return [
    StubApiClientInterface::class => DI\autowire(StubApiClient::class),
    StubBusinessRepositoryInterface::class => DI\autowire(StubBusinessRepository::class),
    StubAuthTokenServiceInterface::class => DI\autowire(StubAuthTokenService::class),
    StubBusinessProvisionerInterface::class => DI\autowire(StubBusinessProvisioner::class),
    StubMigrationServiceInterface::class => DI\autowire(StubMigrationService::class),
];
