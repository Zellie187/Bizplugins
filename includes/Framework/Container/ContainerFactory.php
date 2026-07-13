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
     *
     * @return Container
     */
    public static function create(): Container
    {
        $builder = new ContainerBuilder();

        /*
         * Future configuration:
         *
         * - Enable compilation
         * - Enable definition caching
         * - Register definitions
         * - Environment-specific configuration
         */

        return $builder->build();
    }
}