<?php

declare(strict_types=1);

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\Repositories\ClientRepository;
use BizHub\ClientPortal\Services\ClientService;

return [

    ClientRepositoryInterface::class => DI\autowire(ClientRepository::class),

    ClientServiceInterface::class => DI\autowire(ClientService::class),

];
