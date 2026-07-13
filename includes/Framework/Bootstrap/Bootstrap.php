<?php

declare(strict_types=1);

namespace BizHub\Framework\Bootstrap;

use BizHub\Framework\Application\Application;
use BizHub\Framework\Exceptions\FrameworkException;

/**
 * Framework bootstrapper.
 *
 * Responsible for preparing the BizHub Framework and starting the
 * application lifecycle.
 *
 * @package BizHub\Framework\Bootstrap
 */
final class Bootstrap
{
    /**
     * Indicates whether the framework has already been booted.
     *
     * @var bool
     */
    private static bool $booted = false;

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Boot the BizHub Framework.
     *
     * @throws FrameworkException If the application cannot be started.
     *
     * @return void
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        Constants::register();

        $application = Application::instance();

        $application->boot();

        self::$booted = true;
    }

    /**
     * Determine whether the framework has been booted.
     *
     * @return bool
     */
    public static function isBooted(): bool
    {
        return self::$booted;
    }
}