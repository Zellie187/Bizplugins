<?php

declare(strict_types=1);

use BizHub\Payments\Contracts\PaymentAttemptRepositoryInterface;
use BizHub\Payments\Contracts\PaymentAttemptServiceInterface;
use BizHub\Payments\Contracts\PaymentConfirmationServiceInterface;
use BizHub\Payments\Contracts\PaymentGatewayRegistryInterface;
use BizHub\Payments\Contracts\CustomerProvisionerInterface;
use BizHub\Payments\Contracts\WebhookEventLogRepositoryInterface;
use BizHub\Payments\Repositories\PaymentAttemptRepository;
use BizHub\Payments\Repositories\WebhookEventLogRepository;
use BizHub\Payments\Services\CustomerProvisioner;
use BizHub\Payments\Services\PaymentAttemptService;
use BizHub\Payments\Services\PaymentConfirmationService;
use BizHub\Payments\Services\PaymentGatewayRegistry;

/*
 * Contributed into BizHub's shared container via the
 * 'bizhub/container_definitions' filter - see bizupkeep-payments.php.
 * Concrete classes (gateways, controllers, admin pages, etc.) do not
 * need entries here: PHP-DI autowires them automatically. Only
 * interface -> concrete bindings need to be declared explicitly.
 */
return [
    PaymentAttemptRepositoryInterface::class => DI\autowire(PaymentAttemptRepository::class),
    WebhookEventLogRepositoryInterface::class => DI\autowire(WebhookEventLogRepository::class),

    PaymentGatewayRegistryInterface::class => DI\autowire(PaymentGatewayRegistry::class),
    CustomerProvisionerInterface::class => DI\autowire(CustomerProvisioner::class),

    PaymentAttemptServiceInterface::class => DI\autowire(PaymentAttemptService::class),
    PaymentConfirmationServiceInterface::class => DI\autowire(PaymentConfirmationService::class),
];
