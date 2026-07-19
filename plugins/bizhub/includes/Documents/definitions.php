<?php

declare(strict_types=1);

use BizHub\Documents\Contracts\DocumentRepositoryInterface;
use BizHub\Documents\Repositories\DocumentRepository;

return [

    DocumentRepositoryInterface::class => DI\autowire(DocumentRepository::class),

];
