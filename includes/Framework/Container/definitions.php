<?php

declare(strict_types=1);

use BizHub\Platform\Authorization\Services\AuthorizationService;
use BizHub\Platform\Authorization\Services\CapabilityRegistry;
use BizHub\Platform\Authorization\Services\PolicyResolver;

return [

    AuthorizationService::class => DI\autowire(),

    CapabilityRegistry::class => DI\autowire(),

    PolicyResolver::class => DI\autowire(),

];