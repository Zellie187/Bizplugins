<?php

declare(strict_types=1);

use BizHub\Security\Authorization\Registries\CapabilityRegistry;
use BizHub\Security\Authorization\Services\AuthorizationService;
use BizHub\Security\Authorization\Services\PolicyResolver;

return [

    AuthorizationService::class => DI\autowire(),

    CapabilityRegistry::class => DI\autowire(),

    PolicyResolver::class => DI\autowire(),

];
