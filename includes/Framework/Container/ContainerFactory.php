<?php

declare(strict_types=1);

namespace BizHub\Framework\Container;

use DI\Container;
use DI\ContainerBuilder;

/**
 * Creates the application's dependency injection container.
 *
 * @package BizHub\Framework\Container
 */
final class ContainerFactory
{
    /**
     * Create and configure the DI container.
     */
    public static function create(): Container
    {
        $builder = new ContainerBuilder();

        /*
         * Future production configuration:
         *
         * - Enable compilation
         * - Enable definition caching
         * - Environment-specific configuration
         */
        // if (! defined('WP_DEBUG') || WP_DEBUG === false) {
        //     $builder->enableCompilation(BIZHUB_PLUGIN_PATH . 'cache/container');
        // }

        $builder->addDefinitions(
            __DIR__ . '/definitions.php'
        );

        return $builder->build();
    }
}