<?php

declare(strict_types=1);

use BizHub\Framework\Logging\Handlers\FileHandler;
use BizHub\Framework\Logging\LogManager;
use BizHub\Framework\Logging\Logger;

return [

    LogManager::class => DI\factory(static function (): LogManager {
        $manager = new LogManager();

        $manager->addHandler(
            new FileHandler(BIZHUB_STORAGE_PATH . 'logs/bizhub.log')
        );

        return $manager;
    }),

    Logger::class => DI\autowire(),

];
