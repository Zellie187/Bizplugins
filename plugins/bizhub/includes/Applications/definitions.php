<?php

declare(strict_types=1);

use BizHub\Applications\Contracts\ApplicationRepositoryInterface;
use BizHub\Applications\Contracts\ApplicationServiceInterface;
use BizHub\Applications\Repositories\ApplicationRepository;
use BizHub\Applications\Services\ApplicationService;

return [

    ApplicationRepositoryInterface::class => DI\autowire(ApplicationRepository::class),

    ApplicationServiceInterface::class => DI\autowire(ApplicationService::class),

];
