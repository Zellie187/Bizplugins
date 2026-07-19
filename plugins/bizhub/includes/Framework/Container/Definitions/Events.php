<?php

declare(strict_types=1);

use BizHub\Framework\Events\EventDispatcher;

return [

    EventDispatcher::class => DI\autowire(),

];
