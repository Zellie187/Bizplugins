<?php

declare(strict_types=1);

/*
 * Contributed into BizHub's shared container via the
 * 'bizhub/container_definitions' filter - see bizupkeep-core.php.
 */

use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;
use BizUpKeep\Core\Services\ServiceRepository;

return [
    ServiceRepositoryInterface::class => DI\autowire(ServiceRepository::class),
];
