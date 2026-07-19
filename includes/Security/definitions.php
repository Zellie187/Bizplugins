<?php

declare(strict_types=1);

use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Security\Authorization\Services\AuthorizationService;

return [

    AuthorizationServiceInterface::class => DI\autowire(AuthorizationService::class),

];
