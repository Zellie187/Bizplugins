<?php

declare(strict_types=1);

use BizHub\Framework\Cache\Cache;
use BizHub\Framework\Cache\ObjectCache;

return [

    Cache::class => DI\autowire(ObjectCache::class),

];
