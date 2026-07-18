<?php

declare(strict_types=1);

use BizHub\Companies\Contracts\CompanyRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Contracts\DirectorRepositoryInterface;
use BizHub\Companies\Repositories\CompanyRepository;
use BizHub\Companies\Repositories\DirectorRepository;
use BizHub\Companies\Services\CompanyService;

return [

    CompanyRepositoryInterface::class => DI\autowire(CompanyRepository::class),

    DirectorRepositoryInterface::class => DI\autowire(DirectorRepository::class),

    CompanyServiceInterface::class => DI\autowire(CompanyService::class),

];
